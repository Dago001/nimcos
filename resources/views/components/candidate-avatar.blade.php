@props(['candidate', 'size' => 'large'])
@if ($candidate->photo_path)
    <img src="{{ route('candidate.photo', $candidate) }}?v={{ substr(md5($candidate->photo_path), 0, 8) }}" alt="Photograph of {{ $candidate->displayName() }}"
         class="{{ $size === 'large' ? 'candidate-photo' : 'thumb' }}" decoding="async" fetchpriority="high" width="{{ $size === 'large' ? 104 : 40 }}" height="{{ $size === 'large' ? 104 : 40 }}">
@else
    <span class="{{ $size === 'large' ? 'candidate-initials' : 'thumb-initials' }}" aria-hidden="true">{{ $candidate->initials() }}</span>
@endif
