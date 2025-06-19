<?php
// Use variables passed from the calling script
if (!isset($progress)) {
    $progress = 0; // Default fallback
}
$progressPercent = round($progress);
?>
<div class="progress-bar progress-bar--large" aria-hidden="true">
  <div class="progress-bar__fill" style="width: <?php echo $progressPercent; ?>%;"></div>
</div> 