<?php

namespace Modules\Assessment\Tests\Integration;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Facade;
use Mockery;
use Modules\Assessment\App\Services\AssessmentAssignmentService;
use Modules\Assessment\App\Services\AssessmentAutoAssignService;
use Modules\Assessment\App\Services\AssessmentBulkService;
use Modules\Assessment\App\Services\JobFamilyClassifier;
use Modules\Assessment\App\Models\Assessment;
use Modules\Assessment\App\Models\AssessmentPeriod;
use Modules\Assessment\App\Models\AssessmentPost;
use Modules\Auth\App\Models\User;
use Modules\HR\App\Models\EmployeePosition;
use Modules\HR\App\Models\OrganizationalPosition;
use Modules\HR\App\Models\OrganizationalUnit;
use Modules\HR\App\Services\GtarabarSyncService;
use Modules\HR\App\Services\OrganizationHierarchyService;
use Modules\HR\App\Services\OrganizationalPositionService;
use Modules\HR\App\Services\OrgChartService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

class OrganizationAssignmentTest extends TestCase
{
    private Container $container;
    private Manager $database;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
        Container::setInstance($this->container);
        $this->container->instance('config', new Repository());
        $this->container->instance('log', new NullLogger());
        $this->container->instance('events', new Dispatcher($this->container));
        $this->database = new Manager($this->container);
        $this->database->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]);
        $this->database->setAsGlobal();
        $this->database->bootEloquent();
        $this->container->instance('db', $this->database->getDatabaseManager());
        $this->container->bind('db.schema', fn() => $this->database->schema());
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->container);

        $this->database->schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('mobile')->nullable();
            $table->string('personnel_code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        $migrationPaths = [
            '2026_08_17_124939_create_organizational_units_table.php',
            '2026_08_17_125028_create_employee_positions_table.php',
            '2026_08_17_134038_add_department_ref_to_employee_positions_table.php',
            '2026_08_20_083549_add_custom_fields_to_organizational_units_table.php',
            '2026_09_21_071953_create_organizational_positions_table.php',
            '2026_09_21_072049_add_organizational_position_id_to_employee_positions_table.php',
            '2026_10_04_160000_preserve_local_organizational_position_structure.php',
        ];
        foreach ($migrationPaths as $path) {
            $migration = require dirname(__DIR__, 3) . '/HR/Database/Migrations/' . $path;
            $migration->up();
        }
        $this->database->schema()->create('assessment_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('grade')->nullable();
            $table->string('unit')->nullable();
            $table->string('domain')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        $this->database->schema()->create('assessment_post_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('mapping_type');
            $table->string('post_title_pattern')->nullable();
            $table->unsignedBigInteger('organizational_unit_id')->nullable();
            $table->unsignedBigInteger('assessment_post_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        $this->database->schema()->create('assessments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cycle_id')->nullable();
            $table->unsignedBigInteger('period_id')->nullable();
            $table->unsignedBigInteger('employee_user_id');
            $table->unsignedBigInteger('evaluator_user_id');
            $table->unsignedBigInteger('post_id');
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        $this->database->getDatabaseManager()->disconnect();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        parent::tearDown();
    }

    public function test_chart_parent_wins_over_legacy_job_family(): void
    {
        $unit = $this->unit('عملیات');
        $manager = $this->position($unit, 'مدیر عملیات');
        $employeePosition = $this->position($unit, 'کارشناس', $manager);
        $managerEmployee = $this->employee($manager);
        $employee = $this->employee($employeePosition);
        $this->employee($this->position($unit, 'سرپرست'));
        $hierarchy = new OrganizationHierarchyService();
        $service = new AssessmentAssignmentService($hierarchy, new JobFamilyClassifier());

        self::assertSame($managerEmployee->user_id, $service->evaluatorsFor($employee, $hierarchy->snapshot())->first()->user_id);
    }

    public function test_vacant_parent_is_skipped_and_inactive_users_are_not_evaluators(): void
    {
        $unit = $this->unit('عملیات');
        $director = $this->position($unit, 'مدیر');
        $vacant = $this->position($unit, 'رئیس', $director);
        $worker = $this->position($unit, 'کارشناس', $vacant);
        $activeManager = $this->employee($director);
        $this->employee($vacant, false);
        $employee = $this->employee($worker);
        $hierarchy = new OrganizationHierarchyService();

        self::assertSame($activeManager->user_id, $hierarchy->managerCandidates($employee, $hierarchy->snapshot())->first()->user_id);
    }

    public function test_unit_parent_manager_and_legacy_fallback_follow_the_same_chart(): void
    {
        $head = $this->unit('معاونت');
        $child = $this->unit('عملیات', $head);
        $manager = $this->employee($this->position($head, 'رئیس'));
        $rootEmployee = $this->employee($this->position($child, 'سرپرست'));
        $legacy = $this->employee(null, true, $child, 'سرپرست');
        $hierarchy = new OrganizationHierarchyService();
        $service = new AssessmentAssignmentService($hierarchy, new JobFamilyClassifier());
        $snapshot = $hierarchy->snapshot();

        self::assertSame($manager->user_id, $service->evaluatorsFor($rootEmployee, $snapshot)->first()->user_id);
        self::assertSame($manager->user_id, $service->evaluatorsFor($legacy, $snapshot)->first()->user_id);
    }

    public function test_broken_or_inactive_chart_link_does_not_fall_back_to_peers(): void
    {
        $unit = $this->unit('عملیات');
        $inactive = $this->position($unit, 'کارشناس');
        $inactive->update(['is_active' => false]);
        $employee = $this->employee($inactive);
        $this->employee($this->position($unit, 'سرپرست'));
        $hierarchy = new OrganizationHierarchyService();
        $service = new AssessmentAssignmentService($hierarchy, new JobFamilyClassifier());

        self::assertCount(0, $service->evaluatorsFor($employee, $hierarchy->snapshot()));
    }

    public function test_move_transfers_descendants_and_employees_atomically(): void
    {
        $source = $this->unit('قدیم');
        $target = $this->unit('جدید');
        $root = $this->position($source, 'رئیس', null, 100);
        $child = $this->position($source, 'کارشناس', $root, 101);
        $rootEmployee = $this->employee($root);
        $childEmployee = $this->employee($child);

        (new OrganizationalPositionService())->move($root, null, null, $target->id);

        foreach ([$root, $child] as $position) {
            self::assertEquals($target->id, $position->fresh()->organizational_unit_id);
            self::assertEquals($source->id, $position->fresh()->source_organizational_unit_id);
            self::assertTrue($position->fresh()->is_structure_overridden);
        }
        self::assertEquals($root->id, $child->fresh()->parent_id);
        self::assertEquals($target->id, $rootEmployee->fresh()->organizational_unit_id);
        self::assertEquals($target->id, $childEmployee->fresh()->organizational_unit_id);
    }

    public function test_move_collision_rolls_back_the_entire_subtree(): void
    {
        $source = $this->unit('قدیم');
        $target = $this->unit('جدید');
        $root = $this->position($source, 'رئیس', null, 100);
        $child = $this->position($source, 'کارشناس', $root, 101);
        $employee = $this->employee($root);
        $this->position($target, 'متعارض', null, 101);

        try {
            (new OrganizationalPositionService())->move($root, null, null, $target->id);
            self::fail('Expected a duplicate external position constraint failure.');
        } catch (\Illuminate\Database\QueryException $exception) {
            self::assertEquals($source->id, $root->fresh()->organizational_unit_id);
            self::assertEquals($source->id, $child->fresh()->organizational_unit_id);
            self::assertEquals($source->id, $employee->fresh()->organizational_unit_id);
        }
    }

    public function test_sync_preserves_a_moved_position_and_maps_new_occupants_to_it(): void
    {
        $source = $this->unit('راهکاران');
        $target = $this->unit('داخلی');
        $root = $this->position($source, 'رئیس', null, 100);
        $employee = $this->employee($root);
        (new OrganizationalPositionService())->move($root, null, null, $target->id);

        foreach ([$employee->user, User::create(['name' => 'همکار جدید', 'personnel_code' => 'new'])] as $user) {
            $mock = Mockery::mock(User::class)->makePartial();
            $mock->setRawAttributes($user->getAttributes());
            $mock->shouldReceive('getGtarabarEmployee')->andReturn((object) ['EmployeeID' => 10]);
            $mock->shouldReceive('getEmployeeStatute')->andReturn($this->statute($source, 100));
            $result = (new GtarabarSyncService())->syncUserPosition($mock);
            self::assertNotNull($result);
            self::assertEquals($root->id, $result->organizational_position_id);
            self::assertEquals($target->id, $result->organizational_unit_id);
            self::assertEquals($source->gt_department_id, $result->gt_department_ref);
        }
        self::assertSame(1, OrganizationalPosition::count());
    }

    public function test_source_department_change_replaces_the_previous_local_mapping(): void
    {
        $source = $this->unit('راهکاران');
        $local = $this->unit('داخلی');
        $changedSource = $this->unit('حکم جدید');
        $root = $this->position($source, 'رئیس', null, 100);
        $employee = $this->employee($root);
        (new OrganizationalPositionService())->move($root, null, null, $local->id);
        $mock = Mockery::mock(User::class)->makePartial();
        $mock->setRawAttributes($employee->user->getAttributes());
        $mock->shouldReceive('getGtarabarEmployee')->andReturn((object) ['EmployeeID' => 10]);
        $mock->shouldReceive('getEmployeeStatute')->andReturn($this->statute($changedSource, 100));

        $result = (new GtarabarSyncService())->syncUserPosition($mock);

        self::assertNotNull($result);
        self::assertNotEquals($root->id, $result->organizational_position_id);
        self::assertEquals($changedSource->id, $result->organizational_unit_id);
    }

    public function test_sync_failure_preserves_existing_employee_links(): void
    {
        $unit = $this->unit('عملیات');
        $position = $this->position($unit, 'رئیس', null, 100);
        $employee = $this->employee($position);
        $mock = Mockery::mock(User::class)->makePartial();
        $mock->setRawAttributes($employee->user->getAttributes());
        $mock->shouldReceive('getGtarabarEmployee')->andReturn((object) ['EmployeeID' => 10]);
        $statute = $this->statute($unit, 100);
        $statute->DepartmentRef = 999999;
        $mock->shouldReceive('getEmployeeStatute')->andReturn($statute);

        self::assertNull((new GtarabarSyncService())->syncUserPosition($mock));
        self::assertEquals($position->id, $employee->fresh()->organizational_position_id);
        self::assertEquals($unit->id, $employee->fresh()->organizational_unit_id);
        self::assertTrue($employee->fresh()->sync_failed);
    }

    public function test_chart_endpoints_share_nested_units_and_employee_counts(): void
    {
        $root = $this->unit('معاونت');
        $child = $this->unit('عملیات', $root);
        $employee = $this->employee($this->position($child, 'کارشناس'));
        $management = (new OrganizationalPositionService())->tree();
        $chart = (new OrgChartService())->buildTree();

        self::assertCount(1, $management);
        self::assertEquals($child->id, $management[0]['children'][0]['data']['id']);
        self::assertSame(1, $management[0]['children'][0]['data']['employee_count']);
        self::assertEquals($employee->user_id, $chart[0]['children'][0]['children'][0]['children'][0]['data']['user_id']);
    }

    public function test_cycle_is_rejected_without_recursing_forever(): void
    {
        $unit = $this->unit('عملیات');
        $first = $this->position($unit, 'رئیس');
        $second = $this->position($unit, 'کارشناس', $first);
        $first->update(['parent_id' => $second->id]);
        $this->expectException(RuntimeException::class);

        (new OrganizationHierarchyService())->snapshot();
    }

    public function test_preview_cycle_and_period_generation_use_the_same_evaluator(): void
    {
        $unit = $this->unit('عملیات');
        $managerPosition = $this->position($unit, 'مدیر');
        $manager = $this->employee($managerPosition);
        $employee = $this->employee($this->position($unit, 'کارشناس', $managerPosition));
        AssessmentPost::create(['title' => 'شناسنامه', 'grade' => 'کارشناس', 'unit' => $unit->title]);
        $service = new AssessmentAssignmentService(new OrganizationHierarchyService(), new JobFamilyClassifier());
        $preview = (new AssessmentAutoAssignService($service))->getPreview(1);
        $row = collect($preview['rows'])->firstWhere('user_id', $employee->user_id);
        $period = new AssessmentPeriod();
        $period->id = 5;
        $result = (new AssessmentBulkService($service))->generateForPeriod($period);

        self::assertSame('ready', $row['status']);
        self::assertEquals($manager->user_id, $row['evaluator_id']);
        self::assertSame(1, $result['created']);
        self::assertEquals($row['evaluator_id'], Assessment::first()->evaluator_user_id);
        self::assertSame(0, (new AssessmentBulkService($service))->generateForPeriod($period)['created']);
    }

    public function test_management_routes_have_permissions_registered_before_grouping(): void
    {
        $router = new Router(new Dispatcher($this->container), $this->container);
        $this->container->instance('router', $router);
        require dirname(__DIR__, 3) . '/HR/routes/api.php';
        require dirname(__DIR__, 2) . '/routes/api.php';
        foreach ($router->getRoutes() as $route) {
            $uri = $route->uri();
            $mutates = !in_array($route->methods()[0], ['GET', 'HEAD'], true);
            if (str_starts_with($uri, 'v1/hr/') && $mutates) {
                self::assertContains('permission:hr.manage', $route->middleware(), $uri);
            }
            if (str_starts_with($uri, 'v1/assessment/') && $mutates
                && !str_ends_with($uri, '/submit') && !str_ends_with($uri, '/actions')) {
                self::assertContains('permission:assessment.manage', $route->middleware(), $uri);
            }
        }
    }

    private function unit(string $title, ?OrganizationalUnit $parent = null): OrganizationalUnit
    {
        $unit = OrganizationalUnit::create([
            'title' => $title, 'gt_department_id' => OrganizationalUnit::count() + 100,
            'parent_id' => $parent?->id, 'level' => ($parent?->level ?? 0) + 1,
            'is_active' => true, 'sort_order' => 0,
        ]);
        $unit->update(['path' => ($parent?->path ?? '/') . $unit->id . '/']);

        return $unit;
    }

    private function position(OrganizationalUnit $unit, string $title, ?OrganizationalPosition $parent = null, ?int $reference = null): OrganizationalPosition
    {
        return OrganizationalPosition::create([
            'organizational_unit_id' => $unit->id, 'source_organizational_unit_id' => $unit->id,
            'post_title' => $title, 'parent_id' => $parent?->id, 'gt_post_ref' => $reference,
            'is_active' => true, 'sort_order' => 0,
        ]);
    }

    private function employee(?OrganizationalPosition $position, bool $active = true, ?OrganizationalUnit $unit = null, ?string $title = null): EmployeePosition
    {
        $user = User::create(['name' => 'کارمند', 'personnel_code' => 'p' . (User::count() + 1), 'is_active' => $active]);

        return EmployeePosition::create([
            'user_id' => $user->id, 'personnel_code' => $user->personnel_code,
            'organizational_position_id' => $position?->id,
            'organizational_unit_id' => $position?->organizational_unit_id ?? $unit->id,
            'post_title' => $position?->post_title ?? $title,
        ]);
    }

    private function statute(OrganizationalUnit $unit, int $postReference): object
    {
        return (object) [
            'EmployeeStatuteID' => 12, 'DepartmentRef' => $unit->gt_department_id,
            'PostRef' => $postReference, 'PostCode' => '100', 'PostTitle' => 'رئیس',
            'JobRef' => 20, 'JobCode' => '20', 'JobTitle' => 'رئیس', 'OrganizationalStructureRef' => 30,
        ];
    }
}
