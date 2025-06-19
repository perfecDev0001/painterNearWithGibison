<section class="step step--request-type" aria-labelledby="step-request-type-label">
    <div class="step__icon" aria-hidden="true">🎨</div>
    <div class="step__content">
        <span id="step-request-type-label" class="step__label">Request for</span>
        <div class="step__options">
            <label class="step__option"><input type="radio" name="requesttype" value="Interior" required <?php echo (isset($stepData['requesttype']) && $stepData['requesttype'] === 'Interior') ? 'checked' : ''; ?> /> Interior</label>
            <label class="step__option"><input type="radio" name="requesttype" value="Exterior" required <?php echo (isset($stepData['requesttype']) && $stepData['requesttype'] === 'Exterior') ? 'checked' : ''; ?> /> Exterior</label>
            <label class="step__option"><input type="radio" name="requesttype" value="Both" required <?php echo (isset($stepData['requesttype']) && $stepData['requesttype'] === 'Both') ? 'checked' : ''; ?> /> Interior and Exterior</label>
        </div>
        <button type="submit" class="step__button step__button--next">Next</button>
    </div>
</section> 