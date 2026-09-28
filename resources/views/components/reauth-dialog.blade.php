@props([
    'id',
    'action',
    'title',
    'button' => 'Confirm',
    'variant' => 'primary',
    'method' => 'POST',
    'trigger' => null,
    'triggerClass' => 'btn btn-primary',
])
{{-- Privileged-action confirmation: shows a summary and requires the admin's password (and MFA code). --}}
<button type="button" class="{{ $triggerClass }}" data-dialog-open="{{ $id }}">{{ $trigger ?? $button }}</button>
<dialog class="modal" id="{{ $id }}" aria-labelledby="{{ $id }}-title">
    <form method="POST" action="{{ $action }}" data-submit-once>
        @csrf
        @if (strtoupper($method) !== 'POST')
            @method($method)
        @endif
        <div class="modal-head"><h2 id="{{ $id }}-title">{{ $title }}</h2></div>
        <div class="modal-body">
            {{ $slot }}
            <div class="field">
                <label for="{{ $id }}-pw">Your password <span class="req">*</span></label>
                <input class="input" type="password" id="{{ $id }}-pw" name="confirm_password" autocomplete="current-password" required>
            </div>
            @if (auth('web')->user()?->hasMfa())
                <div class="field">
                    <label for="{{ $id }}-mfa">Authenticator code <span class="req">*</span></label>
                    <input class="input" type="text" id="{{ $id }}-mfa" name="confirm_mfa" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required>
                </div>
            @endif
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-secondary" data-dialog-close>Cancel</button>
            <button type="submit" class="btn btn-{{ $variant }}" data-busy-text="Please wait…">{{ $button }}</button>
        </div>
    </form>
</dialog>
