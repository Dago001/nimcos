@extends('errors.layout')
@section('title', 'We could not complete your request')
@section('code', '500')
@section('heading', 'We could not complete your request')
@section('message', 'Please try again. If the problem continues, contact the election administrator.')
@if (isset($reference))
    @section('reference', $reference)
@endif
