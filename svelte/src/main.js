import './styles/style.css';

import QueueManagerIcon from './components/icons/QueueManagerIcon.svelte';
import { Language } from './utils/Language';
import { UserData } from './utils/UserData';

window.SvelteLanguage = Language;
window.SvelteUserData = UserData;

export {QueueManagerIcon};
