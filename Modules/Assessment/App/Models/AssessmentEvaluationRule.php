<?php
namespace Modules\Assessment\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Acl\App\Models\Group;

class AssessmentEvaluationRule extends Model
{
    protected $table = 'assessment_evaluation_rules';

    protected $fillable = [
        'evaluator_group_id',
        'evaluated_group_id',
    ];

    public function evaluatorGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'evaluator_group_id');
    }

    public function evaluatedGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'evaluated_group_id');
    }
}
