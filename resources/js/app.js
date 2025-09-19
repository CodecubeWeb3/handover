import './bootstrap';
import 'bootstrap/dist/js/bootstrap.bundle';

window.Laravel = window.Laravel || {};
const body = document.body;
if (body && body.dataset.userId) {
    window.Laravel.user = window.Laravel.user || {};
    window.Laravel.user.id = Number(body.dataset.userId);
}