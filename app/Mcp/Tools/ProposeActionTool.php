<?php

namespace App\Mcp\Tools;

use App\Mcp\McpActor;
use App\Models\McpToken;
use App\Services\Ai\Actions\AgentActionProposals;
use App\Services\Ai\Actions\AgentActionRegistry;
use App\Services\Ai\Actions\InvalidActionException;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

/**
 * The first step of a change made from Claude: the admin assistant's action
 * of the same type checks the parameters and says what it would do, but
 * nothing changes. The preview comes back with a proposal id that
 * apply_proposal turns into the real change, once the person has agreed.
 * Only tokens issued with --write see these tools. Subclasses carry
 * #[IsReadOnly] themselves, as nothing but the proposal is stored.
 */
abstract class ProposeActionTool extends ShopTool
{
    /** The sentence every proposing tool ends its description with. */
    protected const TWO_STEP = ' Công cụ này chưa thay đổi dữ liệu: nó trả bản xem trước và proposal_id. Cho người dùng xem bản xem trước, và chỉ khi họ đồng ý mới gọi apply_proposal.';

    /** The AgentAction type this tool proposes. */
    abstract protected function type(): string;

    protected function permissions(): array
    {
        return [app(AgentActionRegistry::class)->action($this->type())->permission()];
    }

    public function shouldRegister(): bool
    {
        return McpToken::current()?->allowsWrite($this->permissions()) ?? false;
    }

    public function handle(Request $request, AgentActionRegistry $registry, AgentActionProposals $proposals, AuditLogger $audit): ResponseFactory|Response
    {
        $this->audit($audit, $request);

        $actor = McpActor::for(McpToken::current());
        $action = $registry->forUser($this->type(), $actor);

        if ($action === null) {
            return Response::error('Vai trò của token này không có quyền làm việc này.');
        }

        try {
            $preview = $action->preview($request->all(), $actor);
        } catch (InvalidActionException $exception) {
            return Response::error($exception->getMessage());
        }

        return Response::structured([
            'proposal_id' => $proposals->store($actor, $this->type(), $preview->params),
            'title' => $preview->title,
            'lines' => $preview->lines,
            'warnings' => $preview->warnings,
            'confirm_label' => $preview->confirmLabel,
            'expires_in_minutes' => AgentActionProposals::TTL_MINUTES,
            'next_step' => 'Chưa có gì thay đổi. Trình bày bản xem trước cho người dùng; chỉ khi họ đồng ý rõ ràng mới gọi apply_proposal với proposal_id này.',
        ]);
    }

    /** A date and time in the shop's time zone, as the admin assistant's actions read them. */
    protected function momentSchema(JsonSchema $schema, string $what): Type
    {
        return $schema->string()->description("{$what}, dạng YYYY-MM-DD HH:MM (giờ Việt Nam); chỉ ghi ngày thì hiểu là cả ngày.");
    }
}
