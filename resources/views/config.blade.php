<!DOCTYPE html>
<html>
<head>
    <title>Config</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" @cspNonce>
</head>
<body class="p-5">
    <div class="container">
        <h1>Configuration</h1>

        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('config.store') }}" method="POST">
            @csrf
            <div class="mb-3">
                <label for="api_key" class="form-label">API Key</label>
                <input type="text" name="api_key" class="form-label w-100" id="api_key" value="{{ old('api_key', $config->api_key) }}">
            </div>
            <div class="mb-3">
                <label for="base_url" class="form-label">Base URL</label>
                <input type="text" name="base_url" class="form-label w-100" id="base_url" value="{{ old('base_url', $config->base_url ?? config('services.foundry.base_url')) }}">
            </div>
            <div class="mb-3">
                <label for="foundry_url" class="form-label">Foundry URL</label>
                <input type="text" name="foundry_url" class="form-label w-100" id="foundry_url" value="{{ old('foundry_url', $config->foundry_url) }}">
            </div>
            <button type="submit" class="btn btn-primary">Save Configuration</button>
        </form>
    </div>
</body>
</html>
