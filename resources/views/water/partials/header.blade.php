<section class="dashboard-hero"><div><p class="eyebrow">CCIS Faculty Club</p><h1>{{ $heading }}</h1><p>Payments &amp; Collections · CCIS Faculty Club</p></div></section>
@include('water.partials.navigation')
@if(session('status'))<p class="water-alert" role="status">{{ session('status') }}</p>@endif
@if(session('water_warning'))<p class="water-alert" role="alert">{{ session('water_warning') }}</p>@endif
@if($errors->any())<div class="water-alert water-error" role="alert"><strong>Please correct the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
