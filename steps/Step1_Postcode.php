<section class="step step--postcode" aria-labelledby="step-postcode-label">
    <div class="step__icon" aria-hidden="true">📍</div>
    <div class="step__content">
        <label id="step-postcode-label" for="postcode" class="step__label">Postcode</label>
        <input type="text" id="postcode" name="postcode" class="step__input" required pattern="[A-Za-z0-9 ]{3,8}" value="<?php echo isset($stepData['postcode']) ? htmlspecialchars($stepData['postcode']) : ''; ?>" />
        <button type="submit" class="step__button step__button--next">Next</button>
    </div>
</section> 