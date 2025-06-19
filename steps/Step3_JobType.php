<section class="step step--job-type" aria-labelledby="step-job-type-label">
    <div class="step__icon" aria-hidden="true">🏢</div>
    <div class="step__content">
        <span id="step-job-type-label" class="step__label">Type of job</span>
        <div class="step__options">
            <label class="step__option"><input type="radio" name="jobtype" value="Residential" required <?php echo (isset($stepData['jobtype']) && $stepData['jobtype'] === 'Residential') ? 'checked' : ''; ?> /> Residential</label>
            <label class="step__option"><input type="radio" name="jobtype" value="Commercial" required <?php echo (isset($stepData['jobtype']) && $stepData['jobtype'] === 'Commercial') ? 'checked' : ''; ?> /> Commercial</label>
        </div>
        <button type="submit" class="step__button step__button--next">Next</button>
    </div>
</section> 