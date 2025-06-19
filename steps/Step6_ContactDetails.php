<section class="step step--contact-details" aria-labelledby="step-contact-details-label">
    <div class="step__icon" aria-hidden="true">👤</div>
    <form method="post" class="step__form">
        <span id="step-contact-details-label" class="step__label">Contact details</span>
        <label class="step__label" for="fullname">Full name</label>
        <input type="text" id="fullname" name="fullname" class="step__input" required />
        <label class="step__label" for="email">Email</label>
        <input type="email" id="email" name="email" class="step__input" required />
        <label class="step__label" for="phone">Phone number</label>
        <input type="tel" id="phone" name="phone" class="step__input" required pattern="[0-9\-\+ ]{10,15}" />
        <button type="submit" class="step__button step__button--next">Get quotes</button>
    </form>
</section> 