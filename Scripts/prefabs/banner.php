<?php
// BANNER component. Optional: set $bannerTitle before including to change the text.
// Expects MASCI_LOGO.png next to the page.
$bannerTitle = $bannerTitle ?? 'Manila Science High School Online Library';
?>
<style>
  .site-banner {
    display: flex; align-items: center; gap: 20px; padding: 20px 26px;
    border: 2px solid #e3a62a;
    background: linear-gradient(90deg, #3351a2 0%, #12347f 55%, #0a2766 100%);
    color: #fff; font-family: "Public Sans", sans-serif;
  }
  .site-banner__logo { width: 60px; height: 60px; flex-shrink: 0; display: block; }
  .site-banner__title { margin: 0; font-size: 22px; font-weight: 600; line-height: 1.25; }
  @media (max-width: 600px) {
    .site-banner { padding: 14px 16px; gap: 12px; }
    .site-banner__logo { width: 44px; height: 44px; }
    .site-banner__title { font-size: 16px; }
  }
</style>
<header class="site-banner">
  <img class="site-banner__logo" src="MASCI_LOGO.png" alt="Manila Science High School seal">
  <div class="site-banner__title"><?= htmlspecialchars($bannerTitle) ?></div>
</header>
