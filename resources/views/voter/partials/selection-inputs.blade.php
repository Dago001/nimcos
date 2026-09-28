@foreach ($selections as $positionId => $candidateIds)
    @foreach ($candidateIds as $candidateId)
        <input type="hidden" name="selections[{{ $positionId }}][]" value="{{ $candidateId }}">
    @endforeach
@endforeach
