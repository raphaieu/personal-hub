@php
    $ga4MeasurementId = (string) config('services.analytics.ga4_measurement_id', '');
@endphp

@if ($ga4MeasurementId !== '')
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga4MeasurementId }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());

        gtag('config', '{{ $ga4MeasurementId }}');
    </script>
@endif
