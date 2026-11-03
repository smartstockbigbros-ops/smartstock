<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
    </main>
</div>
<div class="toast" id="toast" role="status"></div>
<script>window.SS = {
    start: "<?= site_url('sensor/start') ?>",
    stop: "<?= site_url('sensor/stop') ?>",
    readAll: "<?= site_url('alerts/read-all') ?>",
    flash: <?= json_encode($flash ?? null) ?>
};</script>
<script src="<?= base_url('assets/js/smartstock.js') ?>"></script>
</body>
</html>
