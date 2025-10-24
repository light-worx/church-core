<x-layout>
    <h2>Check Environment</h2>
    <ul>
        <li>PHP Version: {{ PHP_VERSION }} - @if($requirements['php_version']) ✅ @else ❌ @endif</li>
        @foreach($requirements['extensions'] as $ext => $ok)
            <li>{{ $ext }} - {{ $ok ? '✅' : '❌' }}</li>
        @endforeach
        @foreach($requirements['permissions'] as $dir => $ok)
            <li>{{ $dir }} writable - {{ $ok ? '✅' : '❌' }}</li>
        @endforeach
    </ul>

    <h3>Database Configuration</h3>
    <form method="POST" action="{{ route('install.environment.save') }}">
        @csrf
        <input class="form-control" name="DB_HOST" placeholder="DB Host" required>
        <input class="form-control" name="DB_PORT" placeholder="DB Port" value="3306">
        <input class="form-control" name="DB_DATABASE" placeholder="DB Name" required>
        <input class="form-control" name="DB_USERNAME" placeholder="DB Username" required>
        <input class="form-control" type="password" name="DB_PASSWORD" placeholder="DB Password">
        <button type="submit" class="btn btn-primary">Continue</button>
    </form>
</x-layout>
