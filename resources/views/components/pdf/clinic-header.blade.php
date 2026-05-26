@props(['clinicHeader' => null])

@if($clinicHeader)
    @php
        $filled = fn ($value): bool => $value !== null && $value !== '';
        $location = trim(collect([$clinicHeader['postal_code'] ?? null, $clinicHeader['city'] ?? null])->filter($filled)->implode(' '));
        $contacts = collect([
            $clinicHeader['phone'] ?? null,
            $clinicHeader['email'] ?? null,
        ])->filter($filled)->all();
    @endphp

    <div style="display:table;width:100%;margin-bottom:10px;padding:10px 12px;border:1px solid #d7deea;border-radius:10px;background:#ffffff;color:#172033;font-family:'Helvetica Neue',Arial,sans-serif;">
        <div style="display:table-cell;width:62%;vertical-align:top;">
            @if($clinicHeader['name'] ?? null)
                <div style="font-size:14px;font-weight:700;line-height:1.2;">{{ $clinicHeader['name'] }}</div>
            @endif
            @if($clinicHeader['address'] ?? null)
                <div style="margin-top:3px;color:#475569;font-size:9px;line-height:1.35;">{{ $clinicHeader['address'] }}</div>
            @endif
            @if($location !== '')
                <div style="color:#475569;font-size:9px;line-height:1.35;">{{ $location }}</div>
            @endif
        </div>

        @if(count($contacts) > 0)
            <div style="display:table-cell;width:38%;vertical-align:top;text-align:right;color:#475569;font-size:9px;line-height:1.45;">
                @foreach($contacts as $contact)
                    <div>{{ $contact }}</div>
                @endforeach
            </div>
        @endif
    </div>
@endif
