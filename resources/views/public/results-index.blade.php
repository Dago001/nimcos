@extends('layouts.voter')

@section('title', 'Published results')

@section('content')
<h1 class="page-title">Published election results</h1>
<p class="lede">Only results that have been verified and officially published by the Returning Officer appear here.</p>

@forelse ($elections as $election)
    <div class="election-card">
        <div class="btn-row spread">
            <div>
                <h2 class="mb-0">{{ $election->name }}</h2>
                <div class="muted small">Published {{ display_time($election->published_at) }} (WAT)</div>
            </div>
            <a class="btn btn-secondary" href="{{ route('public.results.show', $election->code) }}">View results</a>
        </div>
    </div>
@empty
    <div class="alert alert-info">No results have been published yet.</div>
@endforelse
@endsection
