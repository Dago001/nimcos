@props(['candidate', 'size' => 'large'])
@if ($candidate->photo_path)
    <img src="{{ route('candidate.photo', $candidate) }}" alt="Photograph of {{ $candidate->displayName() }}"
         class="{{ $size === 'large' ? 'candidate-photo' : 'thumb' }}" loading="lazy" width="{{ $size === 'large' ? 104 : 40 }}" height="{{ $size === 'large' ? 104 : 40 }}">
@else
    <span class="{{ $size === 'large' ? 'candidate-initials' : 'thumb-initials' }}" aria-hidden="true">{{ $candidate->initials() }}</span>
@endif
