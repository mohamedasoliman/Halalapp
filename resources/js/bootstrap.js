import * as Popper from '@popperjs/core';
import axios from 'axios';
import * as bootstrap from 'bootstrap';

window.Popper = Popper;
window.bootstrap = bootstrap;
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
