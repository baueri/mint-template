<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <title>Named slots (mint-slot)</title>
  </head>
  <body style="font-family: system-ui, sans-serif; max-width: 40rem; margin: 2rem auto;">
    <h1>Named slots</h1>
    <p>Inside a <code>mod-*</code> body, use <code>&lt;mint-slot name="…"&gt;</code> for named regions. The <code>$slot</code> variable is a <code>Slot</code> object: echoing <code>$slot</code> or <code>$slot-&gt;body</code> prints the default slot; <code>$slot-&gt;head</code> (in mustaches) prints a named region. The name <code>body</code> is reserved.</p>

    <mod-card>
      <mint-slot name="head">Featured</mint-slot>
      <p>This paragraph is the default slot.</p>
    </mod-card>
  </body>
</html>
