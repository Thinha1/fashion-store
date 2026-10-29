<?php

namespace App\Mcp\Tools;

use App\Mcp\McpActor;
use App\Models\McpToken;
use App\Services\Ai\Actions\AgentActionProposals;
use App\Services\Ai\Actions\AgentActionRegistry;
use App\Services\Ai\Actions\InvalidActionException;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The second step of a change made from Claude, and the only tool that
 * changes data: it runs a previewed proposal, once, as the role's system
 * account (McpActor), with the role's permissions checked again at this
 * moment. Nothing in the proposal can be altered after the preview.
 */
#[IsReadOnly(false)]
#[IsOpenWorld(false)]
class ApplyProposalTool extends ShopTool
{
    protected string $name = 'apply_proposal';

    protected string $title = 'Thực hiện đề xuất';

    protected string $description = 'Thực hiện thật một đề xuất đã xem trước bằng một công cụ propose_* (xác nhận đơn, soạn phiếu nhập nháp, tạo voucher, giảm giá hàng loạt). CHỈ gọi sau khi người dùng đã xem bản xem trước và đồng ý rõ ràng. Mỗi proposal_id dùng được một lần và hết hạn sau 30 phút.';

    protected function permissions(): array
    {
        return ['orders.manage', 'inventory.manage', 'discounts.manage'];
    }

    public function shouldRegister(): bool
    {
        return McpToken::current()?->allowsWrite($this->permissions()) ?? false;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'proposal_id' => $schema->string()->min(10)->max(64)->required()->description('proposal_id do công cụ propose_* trả về.'),
        ];
    }

    public function handle(Request $request, AgentActionProposals $proposals, AgentActionRegistry $registry, AuditLogger $audit): ResponseFactory|Response
    {
        $request->validate(['proposal_id' => ['required', 'string', 'min:10', 'max:64']]);
        $this->audit($audit, $request);

        $token = McpToken::current();
        $actor = McpActor::for($token);
        $taken = $proposals->take($actor, (string) $request->get('proposal_id'));

        if ($taken === null) {
            return Response::error('Đề xuất này đã hết hạn, đã được thực hiện hoặc không có. Hãy tạo đề xuất mới.');
        }

        $action = $registry->forUser($taken['type'], $actor);
        if ($action === null) {
            return Response::error('Vai trò của token này không có quyền thực hiện việc này.');
        }

        try {
            $result = $action->execute($taken['params'], $actor);
        } catch (InvalidActionException $exception) {
            return Response::error($exception->getMessage());
        }

        $audit->record('ai.action', $actor, null, [
            'type' => $taken['type'],
            'result' => $result->message,
            'problems' => $result->problems,
            'via' => 'mcp',
            'token' => $token->name,
        ], actor: $actor);

        return Response::structured([
            'message' => $result->message,
            'links' => $result->links,
            'problems' => $result->problems,
        ]);
    }
}
