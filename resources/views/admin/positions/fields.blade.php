<div class="field">
    <label for="{{ $prefix }}-name">Name <span class="req">*</span></label>
    <input class="input" id="{{ $prefix }}-name" name="name" value="{{ old('name', $position->name) }}" required maxlength="120">
    @error('name')<div class="error-text">{{ $message }}</div>@enderror
</div>
<div class="field">
    <label for="{{ $prefix }}-desc">Description</label>
    <textarea class="input" id="{{ $prefix }}-desc" name="description" maxlength="2000">{{ old('description', $position->description) }}</textarea>
</div>
<div class="form-grid two">
    <div class="field">
        <label for="{{ $prefix }}-order">Display order <span class="req">*</span></label>
        <input class="input" type="number" id="{{ $prefix }}-order" name="display_order" value="{{ old('display_order', $position->display_order) }}" min="0" max="10000" required>
    </div>
    <div class="field">
        <label for="{{ $prefix }}-seats">Default seats <span class="req">*</span></label>
        <input class="input" type="number" id="{{ $prefix }}-seats" name="default_seats" value="{{ old('default_seats', $position->default_seats) }}" min="1" max="50" required>
    </div>
</div>
