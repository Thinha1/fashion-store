<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Ai\Actions\AgentActionProposals;
use App\Services\Ai\Actions\AgentActionRegistry;
use App\Services\Ai\Actions\InvalidActionException;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The staff member clicked "confirm" on an action card in the assistant:
 * run the previewed action, once, as them, with their permissions checked
 * again at this moment.
 */
class AgentActionController extends Controller
{
    public function __invoke(Request $request, string $proposal, AgentActionProposals $proposals, AgentActionRegistry $registry, AuditLogger $audit): JsonResponse
    {
        $actor = $request->user();
        $taken = $proposals->take($actor, $proposal);

        if (! $taken) {
            return response()->json(['message' => 'Đề xuất này đã hết hạn hoặc đã được thực hiện. Hãy nhờ trợ lý đề xuất lại.'], 410);
        }

        $action = $registry->forUser($taken['type'], $actor);
        if (! $action) {
            return response()->json(['message' => 'Bạn không có quyền thực hiện thao tác này.'], 403);
        }

        try {
            $result = $action->execute($taken['params'], $actor);
        } catch (InvalidActionException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        // The staff member who confirmed is both the actor and the subject; the
        // records the action created keep their own audit entries.
        $audit->record('ai.action', $actor, null, [
            'type' => $taken['type'],
            'result' => $result->message,
            'problems' => $result->problems,
        ], actor: $actor);

        return response()->json([
            'message' => $result->message,
            'links' => $result->links,
            'problems' => $result->problems,
        ]);
    }
}
