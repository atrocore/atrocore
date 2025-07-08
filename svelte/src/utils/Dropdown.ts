/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

import {
    computePosition,
    autoUpdate,
    offset,
    flip,
    shift
} from '@floating-ui/dom';

import DropdownParams from "./interfaces/DropdownParams";

export default class Dropdown {
    referenceEl: HTMLElement;
    floatingEl: HTMLElement;
    floatingListElSelector: string = 'li';
    floatingHandler: Function;
    params?: DropdownParams;
    isOpen: boolean = false;
    autoHide: boolean = true;

    constructor(referenceEl: HTMLElement, floatingEl: HTMLElement, params?: DropdownParams) {
        this.referenceEl = referenceEl;
        this.floatingEl = floatingEl;
        this.params = params;

        if (typeof this.params?.disableAutoHide === 'boolean') {
            this.autoHide = !this.params.disableAutoHide;
        }

        if (typeof this.params?.dropdownListElSelector === 'string') {
            this.floatingListElSelector = this.params.dropdownListElSelector;
        }

        document.addEventListener('click', this.onClickOutside.bind(this));
        referenceEl.addEventListener('click', this.onReferenceElClick.bind(this));
        this.floatingEl.addEventListener('click', this.onDropdownClick.bind(this));
    }

    destroy() {
        this.floatingHandler?.();
        document.removeEventListener('click', this.onClickOutside.bind(this));
        this.referenceEl.removeEventListener('click', this.onReferenceElClick.bind(this));
        this.floatingEl.removeEventListener('click', this.onDropdownClick.bind(this));
    }

    toggle() {
        this.isOpen = !this.isOpen;
        this.updateDropdown();
    }

    open() {
        this.isOpen = true;
        this.updateDropdown();
    }

    close() {
        this.isOpen = false;
        this.updateDropdown();
    }

    private updateDropdown() {
        if (this.isOpen) {
            this.showDropdown();
        } else {
            this.hideDropdown();
        }
    }

    private showDropdown() {
        this.floatingEl.style.display = 'block';

        if (this.params?.onDropdownShow) {
            this.params.onDropdownShow(this.floatingEl);
        }

        this.floatingHandler = autoUpdate(this.referenceEl, this.floatingEl, () => {
            const positionOptions = {
                placement: 'bottom-end',
                strategy: 'fixed',
                middleware: []
            };

            if (this.params?.placement) {
                positionOptions['placement'] = this.params.placement;
            }

            if (this.params?.strategy) {
                positionOptions['strategy'] = this.params.strategy;
            }

            if (typeof this.params?.offset === 'object') {
                positionOptions['middleware'].push(offset(this.params.offset));
            }

            if (typeof this.params?.flip === 'object' && typeof this.params?.shift === 'object') {
                if (positionOptions.placement.includes('-')) {
                    positionOptions.middleware.push(flip(this.params.flip), shift(this.params.shift));
                } else {
                    positionOptions.middleware.push(shift(this.params.shift), flip(this.params.flip));
                }
            } else if (typeof this.params?.flip === 'object') {
                positionOptions.middleware.push(flip(this.params.flip));
            } else if (typeof this.params?.shift === 'object') {
                positionOptions.middleware.push(shift(this.params.shift));
            }

            if (positionOptions.middleware.length === 0) {
                positionOptions.middleware = [offset(5), flip(), shift()];
            }

            computePosition(this.referenceEl, this.floatingEl, positionOptions, {animationFrame: true}).then(({x, y}) => {
                Object.assign(this.floatingEl.style, {
                    left: `${x}px`,
                    top: `${y}px`
                });
            });
        });
    }

    private hideDropdown() {
        this.floatingEl.style.display = 'none';

        if (this.params?.onDropdownHide) {
            this.params.onDropdownHide(this.floatingEl);
        }

        if (this.floatingHandler) this.floatingHandler();
    }

    private onClickOutside(event: MouseEvent) {
        if (!this.floatingEl.parentElement?.contains(event.target as Node)) {
            this.isOpen = false;
            this.updateDropdown();
        }
    }

    private onDropdownClick(event: MouseEvent) {
        if (!this.autoHide) {
            return;
        }

        if (!event.target instanceof HTMLElement) {
            return;
        }

        const target = event.target as HTMLElement;
        if (target.matches(this.floatingListElSelector) || target.closest(this.floatingListElSelector)) {
            this.close();
        }
    }

    private onReferenceElClick(event: MouseEvent) {
        this.toggle();
    }
}