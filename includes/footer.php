<?php $lang = $_SESSION['lang'] ?? 'en'; $isRtl = $lang === 'ar'; ?>
<footer style="background:var(--green-dark);color:rgba(255,255,255,.7);text-align:center;padding:1.5rem;font-size:.85rem;margin-top:auto">
  <div style="margin-bottom:.4rem">
    <span style="color:var(--gold-light);font-weight:700">🕌 <?= $isRtl ? APP_NAME_AR : APP_NAME ?></span>
  </div>
  <div>
    <?= $isRtl
      ? '© 2025 كلية مسقط — عبدالرحمن أحمد البطاشي — جميع الحقوق محفوظة'
      : '© 2025 Muscat College — Abdulrahman Ahmed Al-Battashi — All Rights Reserved'
    ?>
  </div>
</footer>
<script src="/js/app.js"></script>
</body>
</html>
