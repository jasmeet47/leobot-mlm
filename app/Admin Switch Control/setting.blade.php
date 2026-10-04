<div class="card bg-dark text-white p-4">
    <h3>📋 रजिस्ट्रेशन फॉर्म फीचर्स कंट्रोल (On/Off Switches)</h3>
    <form action="{{ route('admin.settings.update') }}" method="POST">
        @csrf
        
        <!-- 1. स्पॉन्सर आईडी टॉगल -->
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="sponsor_id" id="switchSponsor" {{ $settings['sponsor_id'] ? 'checked' : '' }}>
            <label class="form-check-label" for="switchSponsor">Unique Sponsor ID & Name फील्ड चालू रखें</label>
        </div>

        <!-- 2. ईमेल वेरिफिकेशन टॉगल -->
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="email_verify" id="switchEmail" {{ $settings['email_verify'] ? 'checked' : '' }}>
            <label class="form-check-label" for="switchEmail">Verified Email (ईमेल सत्यापन) अनिवार्य करें</label>
        </div>

        <!-- 3. गूगल कैप्चा टॉगल -->
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="google_captcha" id="switchCaptcha" {{ $settings['google_captcha'] ? 'checked' : '' }}>
            <label class="form-check-label" for="switchCaptcha">Google Captcha सुरक्षा चालू रखें</label>
        </div>

        <button type="submit" class="btn btn-primary mt-3">सेटिंग्स सुरक्षित करें 🚀</button>
    </form>
</div>
