<div class="ai-chat-widget" x-data="productAiChat('{{ route('admin.products.ai-assist') }}', '{{ route('admin.products.create') }}')" x-cloak>
    <button type="button" class="ai-chat-toggle" x-on:click="toggle()" :aria-expanded="open.toString()"
            aria-label="Trợ lý AI hỗ trợ đăng sản phẩm">
        <x-icon name="robot" x-show="!open" class="size-5" />
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
            <h2><x-icon name="robot" class="mr-1.5 size-4" /> Trợ lý AI</h2>
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
            <p class="text-xs text-gray-500" x-show="!messages.length">
                Đính kèm ảnh sản phẩm (ảnh đầu là ảnh chung; các ảnh sau nếu là biến thể màu khác, AI sẽ tự gán đúng màu)
                và mô tả nhanh (giá, size, màu, danh mục, thương hiệu nếu có), AI sẽ soạn nội dung đăng sản phẩm giúp bạn.
            </p>
            <template x-for="(message, index) in messages" :key="index">
                <div :class="message.role === 'user' ? 'ai-chat-bubble ai-chat-bubble-user' : 'ai-chat-bubble ai-chat-bubble-assistant'">
                    <template x-if="message.role === 'user'">
                        <div class="space-y-1">
                            <template x-for="block in message.blocks.filter((b) => b.type === 'image')" :key="block.dataUrl">
                                <img :src="block.dataUrl" alt="Ảnh đính kèm" class="h-16 w-16 rounded-lg object-cover">
                            </template>
                            <p class="whitespace-pre-line" x-text="message.blocks.filter((b) => b.type === 'text').map((b) => b.text).join(' ')"></p>
                        </div>
                    </template>
                    <template x-if="message.role === 'assistant'">
                        <p x-text="message.navigateLabel
                            ? `Đã di chuyển đến trang ${message.navigateLabel}.`
                            : (message.setFieldsLabel
                                ? `Đã điền ${message.setFieldsLabel} vào form.`
                                : 'Đã cập nhật nội dung gợi ý bên dưới.')"></p>
                    </template>
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
            </div>

            <div class="ai-chat-bubble ai-chat-bubble-assistant ai-chat-thinking" x-show="loading" x-cloak>
                <span></span><span></span><span></span>
                <span class="ai-chat-thinking-label">Đang suy nghĩ...</span>
            </div>
        </div>

        <div class="ai-chat-composer">
            <p class="ai-chat-error" x-show="error" x-text="error"></p>
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
                <label for="ai-chat-file-input" class="sr-only">Đính kèm ảnh</label>
                <input id="ai-chat-file-input" type="file" x-ref="fileInput" accept="image/jpeg,image/png,image/webp" multiple class="hidden" x-on:change="onFileChange($event)">
                <button type="button" class="admin-action-quiet admin-action !min-h-11 !px-3" x-on:click="$refs.fileInput.click()"
                        aria-label="Đính kèm ảnh">
                    <x-icon name="paperclip" class="size-4" />
                </button>
                <label for="ai-chat-message-input" class="sr-only">Nội dung gửi trợ lý AI</label>
                <textarea id="ai-chat-message-input" x-model="input" rows="2" placeholder="VD: áo sơ mi này giá 350k, có size S M L, màu trắng"
                          class="field flex-1 resize-none text-sm"
                          x-on:keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); send(); }"></textarea>
                <button type="button" class="admin-action admin-action-primary !min-h-11 !px-3"
                        :disabled="loading || (!input.trim() && !attachedImages.length)" x-on:click="send()" aria-label="Gửi">
                    <x-icon name="send" class="size-4" />
                </button>
            </div>
        </div>
    </div>
</div>
