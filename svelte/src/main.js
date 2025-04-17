import 'driver.js/dist/driver.css'
import './styles/style.css';
import 'tippy.js/dist/tippy.css';
import 'tippy.js/dist/border.css';

import {driver} from 'driver.js'
import tippy from 'tippy.js';

import {Language} from './utils/Language';
import {UserData} from './utils/UserData';
import {Notifier} from './utils/Notifier';
import {LayoutManager} from "./utils/LayoutManager";
import {Metadata} from "./utils/Metadata";
import {ModelFactory} from "./utils/ModelFactory";
import {Config} from "./utils/Config";
import {Storage} from "./utils/Storage.ts";
import {Acl} from "./utils/Acl.ts";


import JobManagerIcon from './components/icons/JobManagerIcon.svelte';
import UpdatePanel from "./components/panels/UpdatePanel.svelte";
import LayoutComponent from "./components/admin/layouts/LayoutComponent.svelte";
import ApiRequestComponent from "./components/admin/api-request/ApiRequestComponent.svelte";
// import Script from "./components/fields/Script.svelte";
import Navigation from "./components/layout-profile/navigation/Navigation.svelte";
import Favorites from "./components/layout-profile/navigation/Favorites.svelte";
import TreePanel from "./components/record/TreePanel.svelte";
import RightSideView from "./components/record/RightSideView.svelte";
import BaseHeader from "./components/record/header/BaseHeader.svelte";
import ListHeader from "./components/record/header/ListHeader.svelte";
import DetailHeader from "./components/record/header/DetailHeader.svelte";

window.SvelteLanguage = Language;
window.SvelteUserData = UserData;
window.SvelteNotifier = Notifier;
window.SvelteLayoutManager = LayoutManager;
window.SvelteMetadata = Metadata;
window.SvelteModelFactory = ModelFactory;
window.SvelteConfig = Config;
window.SvelteStorage = Storage;
window.SvelteAcl = Acl;
window.driver = driver;
window.tippy = tippy;

export {
    // Script,
    UpdatePanel,
    JobManagerIcon,
    LayoutComponent,
    RightSideView,
    TreePanel,
    ApiRequestComponent,
    Navigation,
    Favorites,
    BaseHeader,
    ListHeader,
    DetailHeader
};
