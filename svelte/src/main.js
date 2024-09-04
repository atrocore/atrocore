import './styles/style.css';

import {Language} from './utils/Language';
import {UserData} from './utils/UserData';
import {Notifier} from './utils/Notifier';
import {LayoutManager} from "./utils/LayoutManager";
import {Metadata} from "./utils/Metadata";
import {ModelFactory} from "./utils/ModelFactory";

import QueueManagerIcon from './components/icons/QueueManagerIcon.svelte';
import UpdatePanel from "./components/panels/UpdatePanel.svelte";
import LayoutComponent from "./components/admin/layouts/LayoutComponent.svelte";

window.SvelteLanguage = Language;
window.SvelteUserData = UserData;
window.SvelteNotifier = Notifier;
window.SvelteLayoutManager = LayoutManager;
window.SvelteMetadata = Metadata;
window.SvelteModelFactory = ModelFactory;

export {UpdatePanel, QueueManagerIcon, LayoutComponent};
