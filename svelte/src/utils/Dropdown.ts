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
    private readonly referenceEl: HTMLElement;
    private readonly floatingEl: HTMLElement;
    private readonly floatingListElSelector: string = 'li';
    private floatingHandler?: Function;
    private params?: DropdownParams;
    private isOpen: boolean = false;
    private readonly autoHide: boolean = true;
    private readonly usePositionOnly: boolean = false;

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

        if (typeof this.params?.usePositionOnly === 'boolean') {
            this.usePositionOnly = this.params.usePositionOnly;
        }

        if (typeof this.params?.isOpen === 'boolean') {
            this.isOpen = this.params.isOpen;
        }

        referenceEl._dropdown = this;

        if (this.usePositionOnly) {
            this.updateDropdown();
        } else {
            referenceEl.addEventListener('click', this.onReferenceElClick.bind(this));
        }
    }

    destroy() {
        this.floatingHandler?.();
        document.removeEventListener('click', this.onClickOutside.bind(this));
        this.floatingEl.removeEventListener('click', this.onDropdownClick.bind(this));

        if (!this.usePositionOnly) {
            this.referenceEl.removeEventListener('click', this.onReferenceElClick.bind(this));
        }

        if (this.referenceEl._dropdown === this) {
            delete this.referenceEl._dropdown;
        }
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
        if (this.params?.onDropdownShow) {
            this.params.onDropdownShow(this.floatingEl);
        }

        this.floatingHandler = autoUpdate(this.referenceEl, this.floatingEl, () => {
            const positionOptions = {
                placement: 'bottom-start',
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

            computePosition(this.referenceEl, this.floatingEl, positionOptions).then(({x, y}) => {
                const options = {
                    left: `${x}px`,
                    top: `${y}px`,
                    display: 'block',
                };

                if (positionOptions.strategy === 'fixed') {
                    options.position = 'fixed';
                }

                if (document.contains(this.floatingEl)) {
                    Object.assign(this.floatingEl.style, options);
                }
            });
        }, {animationFrame: true});

        document.addEventListener('click', this.onClickOutside.bind(this));
        this.floatingEl.addEventListener('click', this.onDropdownClick.bind(this));
    }

    private hideDropdown() {
        this.floatingEl.style.display = 'none';

        if (this.params?.onDropdownHide) {
            this.params.onDropdownHide(this.floatingEl);
        }

        this.floatingHandler?.();
        document.removeEventListener('click', this.onClickOutside.bind(this));
        this.floatingEl.removeEventListener('click', this.onDropdownClick.bind(this));
    }

    private onClickOutside(event: MouseEvent) {
        if (!this.isOpen) {
            return;
        }

        const target = event.target as Node;
        if (!this.floatingEl.contains(target) &&
            !this.referenceEl.contains(target) &&
            !this.floatingEl.parentElement?.contains(target)) {
            this.isOpen = false;
            this.updateDropdown();
        }
    }

    private onDropdownClick(event: MouseEvent) {
        if (!this.autoHide) {
            return;
        }

        if (!(event.target instanceof HTMLElement)) {
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