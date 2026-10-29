<div class="ai-chat-widget" x-data="productAiChat('{{ route('admin.products.ai-assist') }}', '{{ route('admin.products.create') }}', '{{ route('admin.products.ai-assist-stream') }}', '{{ route('admin.products.ai-pdf.read') }}', '{{ route('admin.ai.actions.confirm', ['proposal' => '00000000-0000-4000-8000-000000000000']) }}'.replace('00000000-0000-4000-8000-000000000000', '__PROPOSAL__'))" x-cloak>
    <button type="button" class="ai-chat-toggle" x-on:click="toggle()" :aria-expanded="open.toString()"
            aria-label="Trợ lý AI hỗ trợ đăng sản phẩm">
        <x-ai-bot x-show="!open" />
        <x-icon name="close" x-show="open" class="size-5" />
    </button>

    <div class="ai-chat-panel" x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-3 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-2 scale-95"
         x-on:click.outside="open = false" role="dialog"
         aria-label="Trợ lý AI">
        <div class="ai-chat-panel-header">
            <h2><x-ai-bot /> Trợ lý AI</h2>
            <div class="flex items-center gap-1">
                <button type="button" class="admin-action-quiet admin-action !min-h-8 !px-2" x-show="messages.length"
                        x-on:click="startNewConversation()" title="Cuộc trò chuyện mới" aria-label="Cuộc trò chuyện mới">
                    <x-icon name="plus" class="size-4" />
                </button>
                <button type="button" class="admin-action-quiet admin-action !min-h-8 !px-2" x-on:click="open = false" aria-label="Đóng">
                    <x-icon name="close" class="size-4" />
                </button>
            </div>
        </div>

        <div class="ai-chat-messages" x-ref="messageList">
            <p class="text-sm leading-relaxed text-gray-500" x-show="!messages.length">
                Đính kèm ảnh sản phẩm (ảnh đầu là ảnh chung; các ảnh sau nếu là biến thể màu khác, AI sẽ tự gán đúng màu)
                và mô tả nhanh (giá, size, màu, danh mục, thương hiệu nếu có), AI sẽ soạn nội dung đăng sản phẩm giúp bạn.
                Có thể đính kèm cả file PDF (catalog, phiếu thông số, báo giá) có chữ để AI đọc.
                Trợ lý cũng tạo được voucher, giảm giá hàng loạt, xác nhận đơn và soạn phiếu nhập cho hàng sắp hết;
                mọi thao tác đều chờ bạn bấm xác nhận.
            </p>
            <template x-for="(message, index) in messages" :key="index">
                <div>
                <template x-if="message.role === 'assistant' && message.toolCalls?.length">
                    <div class="ai-chat-tools" x-data="{ open: false }">
                        <button type="button" class="ai-chat-tools-toggle" x-on:click="open = !open" :aria-expanded="open.toString()">
                            <span x-text="`Đã dùng ${message.toolCalls.length} công cụ`"></span>
                            <x-icon name="chevron-down" class="ai-chat-tools-chevron size-3" />
                        </button>
                        <div class="ai-chat-tool-calls-wrap" :class="{ 'is-open': open }" :inert="!open">
                            <div class="ai-chat-tool-calls">
                                <template x-for="(call, callIndex) in message.toolCalls" :key="callIndex + '-' + call.name">
                                    <div class="ai-chat-tool-call" x-data="{ open: false }">
                                        <button type="button" class="ai-chat-tools-toggle" x-on:click="open = !open" :aria-expanded="open.toString()">
                                            <span class="ai-chat-tool-name" x-text="call.name"></span>
                                            <x-icon name="chevron-down" class="ai-chat-tools-chevron size-3" />
                                        </button>
                                        <div class="ai-chat-tool-calls-wrap" :class="{ 'is-open': open }" :inert="!open">
                                            <div class="ai-chat-tool-calls">
                                                <pre x-text="JSON.stringify(call.args, null, 2)"></pre>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
                <div :class="message.isRecap
                    ? 'ai-chat-recap-note'
                    : (message.role === 'user' ? 'ai-chat-bubble ai-chat-bubble-user' : 'ai-chat-bubble ai-chat-bubble-assistant')">
                    <template x-if="message.isRecap">
                        <p x-text="message.blocks[0].text"></p>
                    </template>
                    <template x-if="!message.isRecap && message.role === 'user'">
                        <div class="space-y-1">
                            <template x-for="block in message.blocks.filter((b) => b.type === 'image')" :key="block.dataUrl">
                                <img :src="block.dataUrl" alt="Ảnh đính kèm" class="h-16 w-16 rounded-lg object-cover">
                            </template>
                            <template x-for="doc in documentsOf(message)" :key="doc.name">
                                <p class="ai-chat-doc-chip"><i class="fa-solid fa-file-pdf" aria-hidden="true"></i> <span x-text="doc.label"></span></p>
                            </template>
                            <p class="whitespace-pre-line" x-text="message.blocks.filter((b) => b.type === 'text' && !b.document).map((b) => b.text).join(' ')"></p>
                        </div>
                    </template>
                    <template x-if="message.role === 'assistant'">
                        <div>
                            <p x-text="message.notice
                                ? message.notice
                                : message.actions?.length
                                ? 'Mình đã chuẩn bị thao tác bên dưới. Bạn kiểm tra rồi bấm xác nhận nhé.'
                                : message.navigateLabel
                                ? `Đã di chuyển đến trang ${message.navigateLabel}.`
                                : (message.setFieldsLabel
                                    ? `Đã điền ${message.setFieldsLabel} vào form.`
                                    : 'Đã cập nhật nội dung gợi ý bên dưới.')"></p>
                            <p class="mt-1 text-xs" x-show="message.variantNote" x-text="message.variantNote"
                               :class="/gỡ|Không có biến thể/.test(message.variantNote ?? '') ? 'font-medium text-amber-700' : 'text-gray-600'"></p>
                            <template x-for="(card, cardIndex) in message.actions ?? []" :key="index + '-action-' + cardIndex">
                                <div class="ai-chat-action" :data-state="card.state">
                                    <template x-if="card.state === 'error'">
                                        <p class="ai-chat-action-error" x-text="card.error"></p>
                                    </template>
                                    <template x-if="card.state !== 'error'">
                                        <div>
                                            <p class="ai-chat-action-title" x-text="card.title"></p>
                                            <ul class="ai-chat-action-lines">
                                                <template x-for="(line, lineIndex) in card.lines" :key="lineIndex">
                                                    <li x-text="line"></li>
                                                </template>
                                            </ul>
                                            <template x-for="(warning, warningIndex) in card.warnings" :key="'w' + warningIndex">
                                                <p class="ai-chat-action-warning" x-text="warning"></p>
                                            </template>
                                            <div class="ai-chat-action-buttons" x-show="card.state === 'pending' || card.state === 'running'">
                                                <button type="button" class="admin-action admin-action-primary" x-on:click="confirmAction(card)"
                                                        :disabled="card.state === 'running'" x-text="card.state === 'running' ? 'Đang thực hiện…' : card.confirm_label"></button>
                                                <button type="button" class="admin-action admin-action-secondary" x-on:click="cancelAction(card)" :disabled="card.state === 'running'">Huỷ</button>
                                            </div>
                                            <p class="ai-chat-action-note" x-show="card.state === 'cancelled'">Đã huỷ, không có gì thay đổi.</p>
                                            <template x-if="card.result">
                                                <div class="ai-chat-action-result" role="status">
                                                    <p x-text="card.result.message"></p>
                                                    <template x-for="(problem, problemIndex) in card.result.problems" :key="'p' + problemIndex">
                                                        <p class="ai-chat-action-warning" x-text="problem"></p>
                                                    </template>
                                                    <template x-for="(link, linkIndex) in card.result.links" :key="'l' + linkIndex">
                                                        <a class="ai-chat-action-link" :href="link.url" x-text="link.label"></a>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <button type="button" class="mt-1 block text-xs font-semibold text-brand underline underline-offset-4"
                                    x-show="lastFormChange && lastFormChangeMessageIndex === index && index === messages.length - 1"
                                    x-on:click="undoLastFormChange()">
                                Hoàn tác thay đổi vừa rồi
                            </button>
                        </div>
                    </template>
                </div>
                </div>
            </template>

            <div class="ai-chat-draft-card" x-show="draft && draftMessageIndex === messages.length - 1">
                <template x-if="draft">
                    <dl>
                        <dt>Tên sản phẩm</dt>
                        <dd x-text="draft.name || '—'"></dd>
                        <dt>Mô tả</dt>
                        <dd x-text="draft.description || '—'"></dd>
                        <dt>Điểm nổi bật</dt>
                        <dd x-text="draft.bullets?.length ? draft.bullets.join(' · ') : '—'"></dd>
                        <dt>Tiêu đề SEO</dt>
                        <dd x-text="draft.seo_title || '—'"></dd>
                        <dt>Giá</dt>
                        <dd x-text="draft.price || '—'"></dd>
                        <dt>Tồn kho mỗi biến thể</dt>
                        <dd x-text="draft.stock_quantity || '—'"></dd>
                        <dt>Size</dt>
                        <dd x-text="draft.sizes?.length ? draft.sizes.join(', ') : '—'"></dd>
                        <dt>Màu</dt>
                        <dd x-text="draft.colors?.length ? draft.colors.join(', ') : '—'"></dd>
                        <dt>Danh mục</dt>
                        <dd x-text="draft.category || '— (chọn thủ công)'"></dd>
                        <dt>Thương hiệu</dt>
                        <dd x-text="draft.brand || '— (chọn thủ công)'"></dd>
                    </dl>
                </template>
                <button type="button" class="admin-action admin-action-primary w-full justify-center" x-on:click="fillForm()">
                    Điền vào form
                </button>
                <div class="flex items-center justify-between gap-2 text-xs text-gray-500" x-show="canUndoDraftFill()">
                    <span>Đã điền vào form. Bấm "Điền vào form" lần nữa sẽ ghi đè dữ liệu hiện tại.</span>
                    <button type="button" class="shrink-0 font-semibold text-brand underline underline-offset-4" x-on:click="undoLastFormChange()">
                        Hoàn tác
                    </button>
                </div>
            </div>

            <div class="ai-chat-bubble ai-chat-bubble-assistant ai-chat-thinking" x-show="loading" x-cloak>
                <span></span><span></span><span></span>
                <span class="ai-chat-thinking-label" x-text="streamStatus || 'Đang suy nghĩ...'"></span>
            </div>
        </div>

        <div class="ai-chat-composer">
            <p class="ai-chat-error" x-show="error" x-text="error"></p>
            <div class="flex flex-wrap gap-2" x-show="attachedDocuments.length">
                <template x-for="(doc, index) in attachedDocuments" :key="doc.name">
                    <div class="ai-chat-attachment">
                        <i class="fa-solid fa-file-pdf text-red-500" aria-hidden="true"></i>
                        <span class="min-w-0 flex-1 truncate" x-text="`${doc.name} · ${doc.pages} trang${doc.truncated ? ' (đã cắt bớt)' : ''}`"></span>
                        <button type="button" class="text-gray-400 hover:text-gray-700" x-on:click="removeAttachedDocument(index)" aria-label="Bỏ file PDF">
                            <x-icon name="close" class="size-3.5" />
                        </button>
                    </div>
                </template>
            </div>
            <div class="flex flex-wrap gap-2" x-show="attachedImages.length">
                <template x-for="(image, index) in attachedImages" :key="image.imageIndex">
                    <div class="ai-chat-attachment">
                        <img :src="image.dataUrl" alt="" class="size-6 rounded object-cover">
                        <span class="min-w-0 flex-1 truncate" x-text="image.name"></span>
                        <button type="button" class="text-gray-400 hover:text-gray-700" x-on:click="removeAttachedImage(index)" aria-label="Bỏ ảnh">
                            <x-icon name="close" class="size-3.5" />
                        </button>
                    </div>
                </template>
            </div>
            <div class="flex items-end gap-2">
                <label for="ai-chat-file-input" class="sr-only">Đính kèm ảnh hoặc PDF</label>
                <input id="ai-chat-file-input" type="file" x-ref="fileInput" accept="image/jpeg,image/png,image/webp,application/pdf,.pdf" multiple class="hidden" x-on:change="onFileChange($event)">
                <button type="button" class="admin-action-quiet admin-action !min-h-11 !px-3" x-on:click="$refs.fileInput.click()"
                        aria-label="Đính kèm ảnh hoặc PDF">
                    <x-icon name="paperclip" class="size-4" />
                </button>
                <label for="ai-chat-message-input" class="sr-only">Nội dung gửi trợ lý AI</label>
                <textarea id="ai-chat-message-input" x-ref="messageInput" x-model="input" rows="2" :placeholder="document.getElementById('discount-form') ? 'VD: freeship đơn từ 500k, tự động áp dụng, đến hết tháng' : 'VD: áo sơ mi này giá 350k, có size S M L, màu trắng'"
                          class="field flex-1 resize-none text-sm"
                          x-on:keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); send(); }"></textarea>
                <button type="button" class="admin-action admin-action-primary !min-h-11 !px-3"
                        :disabled="loading || (!input.trim() && !attachedImages.length && !attachedDocuments.length)" x-on:click="send()" aria-label="Gửi">
                    <x-icon name="send" class="size-4" />
                </button>
            </div>
        </div>
    </div>
</div>
