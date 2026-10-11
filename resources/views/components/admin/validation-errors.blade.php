@if ($errors->any())
    <div class="ops-error-summary" role="alert">
        <strong>Periksa kembali data yang dimasukkan.</strong>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
