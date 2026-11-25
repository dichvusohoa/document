import { AutoForm } from 'autoForm';
import { buildActionUrl } from 'url';
const strPostUrl = buildActionUrl('login', {response_type: 'json'});
let autoFrm = new AutoForm(document.forms["frm_login"], strPostUrl);