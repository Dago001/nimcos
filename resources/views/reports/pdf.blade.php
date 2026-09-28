<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        @page { margin: 22mm 14mm 18mm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 9.5px; color: #17201a; }
        .head { border-bottom: 3px solid #207027; padding-bottom: 8px; margin-bottom: 12px; }
        .head img { width: 46px; height: 46px; float: left; margin-right: 10px; border-radius: 23px; }
        .head h1 { font-size: 15px; margin: 0; color: #0c2e10; }
        .head .sub { color: #5d6a61; font-size: 10px; margin-top: 2px; }
        .org { font-size: 9px; letter-spacing: .06em; color: #207027; font-weight: bold; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #eef6ef; color: #0c2e10; text-align: left; font-size: 8.5px; text-transform: uppercase; padding: 5px; border-bottom: 1px solid #c3cdc5; }
        td { padding: 4px 5px; border-bottom: 1px solid #e3e8e4; vertical-align: top; }
        .summary td { border: 0; padding: 2px 8px 2px 0; }
        .summary { margin-bottom: 12px; width: auto; }
        .foot { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 8px; color: #5d6a61; border-top: 1px solid #dbe2dc; padding-top: 3px; }
        .clear { clear: both; }
    </style>
</head>
<body>
<div class="head">
    @if ($logo)<img src="{{ $logo }}" alt="">@endif
    <div class="org">Nigeria Immigration Multi-Purpose Cooperative Society · NIMCOS E-VOTING</div>
    <h1>{{ $report['title'] }}</h1>
    <div class="sub">{{ $report['subtitle'] }} · Generated {{ display_time(now(), 'j F Y, H:i') }} WAT</div>
    <div class="clear"></div>
</div>

@if ($report['summary'])
    <table class="summary">
        @foreach ($report['summary'] as $k => $v)
            <tr><td><strong>{{ $k }}</strong></td><td>{{ $v }}</td></tr>
        @endforeach
    </table>
@endif

<table>
    <thead><tr>@foreach ($report['headings'] as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
    <tbody>
    @forelse ($report['rows'] as $row)
        <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
    @empty
        <tr><td colspan="{{ count($report['headings']) }}">No records.</td></tr>
    @endforelse
    </tbody>
</table>

<div class="foot">NIMCOS E-VOTING · This report respects ballot secrecy: it never links a voter to their choices.</div>
</body>
</html>
