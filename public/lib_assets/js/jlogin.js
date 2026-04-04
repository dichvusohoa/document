import { AutoForm } from 'autoForm';
import { getFormSubmitUrl } from 'url';
const strPostUrl = getFormSubmitUrl() + "?response_type=json";
let autoFrm = new AutoForm(document.forms["frm_login"], strPostUrl);