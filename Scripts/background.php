<?php
// BACKGROUND component: navy frame + rounded photo panel with a dark blue tint.
// Include it right after <body>. Expects MASCI_FACADE_2.jpg next to the page.
?>
<style>
  :root { --frame-pad: 20px; }
  .bg-frame { position: fixed; inset: 0; z-index: 0; padding: var(--frame-pad); background: #0b2a66; }
  .bg-photo {
    width: 100%; height: 100%; border-radius: 12px;
    background-image: linear-gradient(rgba(8, 32, 86, 0.68), rgba(8, 32, 86, 0.68)), url('MASCI_FACADE_2.jpg');
    background-size: cover; background-position: center;
  }
</style>
<div class="bg-frame" aria-hidden="true"><div class="bg-photo"></div></div>
