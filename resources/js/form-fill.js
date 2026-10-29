// DOM helpers the admin chat widget uses to write into a form on screen (the product form
// and the discount form): set a value or a checkbox the way a user would, then flash it.

/**
 * Briefly highlights a field the AI just wrote into, so the staff member can
 * see at a glance what changed without having to scan the whole form — pure
 * CSS (a fading ring/background via `.ai-chat-field-flash`, see admin.css),
 * never `.focus()`, since keyboard focus should stay in the chat box so
 * they can keep typing right away.
 */
export function flashField(el) {
    if (!el) return;
    el.classList.remove('ai-chat-field-flash');
    void el.offsetWidth; // forces a reflow so re-triggering the animation on the same element restarts it
    el.classList.add('ai-chat-field-flash');
    el.addEventListener('animationend', () => el.classList.remove('ai-chat-field-flash'), { once: true });
}

/**
 * Sets a form field's value through its native setter (not just `.value =`)
 * so frameworks/plugins hooking the native input event — Alpine's x-model,
 * the currency mask plugin — see the change, then fires input+change for
 * anything listening the plain way. Works whether the field belongs to a
 * plain PHP-rendered form or one later rewritten with a JS framework.
 */
export function setFieldValue(el, value) {
    if (!el) return;
    const proto = { TEXTAREA: HTMLTextAreaElement, SELECT: HTMLSelectElement }[el.tagName]?.prototype ?? HTMLInputElement.prototype;
    Object.getOwnPropertyDescriptor(proto, 'value').set.call(el, value);
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
    flashField(el);
}

/** Sets a checkbox's checked state (e.g. the "Đang kinh doanh" status toggle) through its native setter. */
export function setCheckboxChecked(el, checked) {
    if (!el) return;
    Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'checked').set.call(el, checked);
    el.dispatchEvent(new Event('change', { bubbles: true }));
    // The checkbox itself is tiny — flash its whole label so the change is
    // actually noticeable ("Đang kinh doanh" text + box together).
    flashField(el.closest('label') ?? el);
}
