<div class="ai-chat-widget" x-data="shoppingAssistChat('{{ route('products.assist') }}', '{{ route('products.assist-stream') }}')" x-cloak>
    <button type="button" class="ai-chat-toggle" x-on:click="toggle()" :aria-expanded="open.toString()"
            aria-label="Trợ lý gợi ý sản phẩm">
        <x-icon name="robot" x-show="!open" class="size-4" />
        <x-icon name="close" x-show="open" class="size-4" />
    </button>

    <div class="ai-chat-panel" x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-3 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-2 scale-95"
         x-on:click.outside="open = false" role="dialog"
         aria-label="Trợ lý gợi ý sản phẩm">
        <div class="ai-chat-panel-header">
            <h2><x-icon name="robot" class="mr-1.5 size-4" /> Gợi ý cho bạn</h2>
            <div class="flex items-center gap-1">
                <button type="button" class="flex size-8 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-700" x-show="messages.length"
                        x-on:click="startNewConversation()" title="Cuộc trò chuyện mới" aria-label="Cuộc trò chuyện mới">
                    <x-icon name="plus" class="size-4" />
                </button>
                <button type="button" class="flex size-8 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-700" x-on:click="open = false" aria-label="Đóng">
                    <x-icon name="close" class="size-4" />
                </button>
            </div>
        </div>

        <div class="ai-chat-messages" x-ref="messageList">
            <div x-show="!messages.length">
                <p class="text-xs text-gray-500">
                    Cho mình biết bạn đang tìm gì (loại trang phục, giá, size, màu, dịp mặc...), mình sẽ gợi ý sản phẩm
                    phù hợp trong shop. Bấm vào sản phẩm mình gợi ý để xem chi tiết và tự thêm vào giỏ nhé.
                </p>
                <div class="mt-3 flex flex-wrap gap-1.5">
                    <template x-for="prompt in quickPrompts" :key="prompt">
                        <button type="button" class="rounded-full border border-gray-200 px-3 py-1.5 text-xs text-gray-600 hover:border-brand hover:text-brand"
                                x-on:click="sendQuickReply(prompt)" x-text="prompt"></button>
                    </template>
                </div>
            </div>
            <template x-for="(message, index) in messages" :key="index">
                <div :class="message.role === 'user' ? 'ai-chat-bubble ai-chat-bubble-user' : 'ai-chat-bubble ai-chat-bubble-assistant'">
                    <template x-if="message.role === 'user'">
                        <p class="whitespace-pre-line" x-text="message.text"></p>
                    </template>
                    <template x-if="message.role === 'assistant'">
                        <div>
                            <p class="whitespace-pre-line" x-text="message.reply"></p>
                            <div class="mt-2 space-y-2" x-show="message.products?.length">
                                <template x-for="product in message.products" :key="product.id">
                                    <a :href="product.url" class="shop-chat-card">
                                        <img :src="product.image_url" :alt="product.name" class="shop-chat-card-image" x-show="product.image_url">
                                        <div class="shop-chat-card-body">
                                            <p class="shop-chat-card-brand" x-text="product.brand ?? 'Fashion Store'"></p>
                                            <p class="shop-chat-card-name" x-text="product.name"></p>
                                            <p class="shop-chat-card-price" x-text="product.price"></p>
                                            <p class="shop-chat-card-stock" x-show="!product.in_stock">Hết hàng</p>
                                        </div>
                                    </a>
                                </template>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-1.5" x-show="index === messages.length - 1">
                                <template x-for="prompt in refinePrompts" :key="prompt">
                                    <button type="button" class="rounded-full border border-gray-200 px-2.5 py-1 text-[11px] text-gray-600 hover:border-brand hover:text-brand"
                                            x-on:click="sendQuickReply(prompt)" x-text="prompt"></button>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <div class="ai-chat-bubble ai-chat-bubble-assistant ai-chat-thinking" x-show="loading" x-cloak>
                <span></span><span></span><span></span>
                <span class="ai-chat-thinking-label" x-text="streamStatus || 'Đang tìm sản phẩm...'"></span>
            </div>
        </div>

        <div class="ai-chat-composer">
            <p class="ai-chat-error" x-show="error" x-text="error"></p>
            <div class="flex items-end gap-2">
                <label for="shop-chat-message-input" class="sr-only">Nội dung gửi trợ lý</label>
                <textarea id="shop-chat-message-input" x-model="input" rows="2" placeholder="VD: áo sơ mi công sở nữ dưới 500k size M"
                          class="field flex-1 resize-none text-sm"
                          x-on:keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); send(); }"></textarea>
                <button type="button" class="btn btn-primary !min-h-11 !px-3"
                        :disabled="loading || !input.trim()" x-on:click="send()" aria-label="Gửi">
                    <x-icon name="send" class="size-4" />
                </button>
            </div>
        </div>
    </div>
</div>
