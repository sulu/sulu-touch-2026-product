import { Controller } from '@hotwired/stimulus';

// Submits the filter form as soon as a checkbox or a range field changes.
export default class extends Controller {
    static targets = ['form'];

    submit() {
        this.formTarget.requestSubmit();
    }
}
