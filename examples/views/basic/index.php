<div>
  <h1>Hello {{ $name }}</h1>

  <p>Escaped output via triple mustache:</p>
  <div>{{{ $name }}}</div>

  <p>Raw output via double mustache:</p>
  <div>{{ $rawHtml }}</div>

  <p>Escaped output html:</p>
  <div>{{{ $rawHtml }}}</div>
</div>
