/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:view', 'class-replace!treo-core:view',
    Dep => Dep.extend({

        pipelines: {},

        initialize(options) {
            if (options && options.helper) {
                this.loadPipelines(options.helper.metadata, () => {
                    Dep.prototype.initialize.call(this, options);
                });
            }
        },

        loadPipelines(metadata, callback) {
            let pipes = [];

            (Object.values(this.pipelines) || []).forEach(pipeline => {
                (metadata.get(pipeline) || []).forEach(pipe => {
                    pipes.push(pipe);
                });
            });

            if (pipes.length) {
                Espo.loader.require(pipes, () => {
                    callback();
                });
            } else {
                callback();
            }
        },

        runPipeline(pipeline, data) {
            if (this.pipelines[pipeline]) {
                let result = data;
                (this.getMetadata().get(this.pipelines[pipeline]) || []).forEach(current => {
                    this._factory.create(current, {}, view => {
                        result = view.runPipe.call(this, result);
                    });
                });
                return result;
            }
        },

        runPipelineAsync(pipeline, data) {
            if (this.pipelines[pipeline]) {
                return (this.getMetadata().get(this.pipelines[pipeline]) || []).reduce((prev, current) => {
                    return prev.then(result => {
                        return new Promise(resolve => {
                            this._factory.create(current, {}, view => {
                                resolve(view.runPipe.call(this, result));
                            });
                        });
                    });
                }, new Promise(resolve => resolve(data)));
            }
        },

    })
);
