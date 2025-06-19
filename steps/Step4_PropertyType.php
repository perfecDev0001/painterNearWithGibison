<section class="step step--property-type" aria-labelledby="step-property-type-label">
    <div class="step__icon" aria-hidden="true">🏠</div>
    <div class="step__content">
        <span id="step-property-type-label" class="step__label">Your property</span>
        <div class="step__options">
            <label class="step__option"><input type="radio" name="propertytype" value="House" required <?php echo (isset($stepData['propertytype']) && $stepData['propertytype'] === 'House') ? 'checked' : ''; ?> /> House</label>
            <label class="step__option"><input type="radio" name="propertytype" value="Apartment/Flat" required <?php echo (isset($stepData['propertytype']) && $stepData['propertytype'] === 'Apartment/Flat') ? 'checked' : ''; ?> /> Apartment/Flat</label>
        </div>
        <button type="submit" class="step__button step__button--next">Next</button>
    </div>
</section> 