import template from './alt-text-generator-index.html.twig';
import './alt-text-generator-index.scss';
import { LANGUAGES } from '../../languages';
import { LOGO } from '../../logo';

const { Component, Mixin } = Shopware;

const CONCURRENCY = 3;

Component.register('alt-text-generator-index', {
    template,

    inject: ['altTextGeneratorApiService'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            logo: LOGO,
            languages: LANGUAGES,
            settings: { hasApiKey: true, autoGenerate: false, language: 'english' },
            credits: null,
            allImages: [],
            images: [],
            counts: { images: 0, missing: 0 },
            filter: 'missing',
            loading: true,
            error: '',
            selected: [],
            drafts: {},
            working: [],
            failed: {},
            overwrite: false,
            running: false,
            saving: false,
            stopRequested: false,
        };
    },

    computed: {
        targets() {
            const picked = this.images.filter((image) => this.selected.includes(image.id));
            if (picked.length > 0) {
                return { list: picked, label: this.$tc('alt-text-generator.page.generateSelected', picked.length, { count: picked.length }) };
            }

            const list = this.images.filter((image) => {
                return this.overwrite || (!image.alt && this.drafts[image.id] === undefined);
            });

            return { list, label: this.$tc('alt-text-generator.page.generateMissing', list.length, { count: list.length }) };
        },

        changed() {
            return Object.entries(this.drafts).filter(([id, text]) => {
                const image = this.images.find((item) => item.id === id);
                return image && text.trim() !== (image.alt || '').trim();
            });
        },

        emptyMessage() {
            if (this.counts.images === 0) {
                return this.$tc('alt-text-generator.page.noImages');
            }

            return this.filter === 'missing'
                ? this.$tc('alt-text-generator.page.allHaveAlt')
                : this.$tc('alt-text-generator.page.nothingToShow');
        },
    },

    created() {
        this.loadSettings();
        this.loadCredits();
        this.scan();
    },

    methods: {
        errorOf(error, fallback) {
            return error?.response?.data?.error || error?.response?.data?.errors?.[0]?.detail || error?.message || fallback;
        },

        async loadSettings() {
            try {
                this.settings = await this.altTextGeneratorApiService.getSettings();
            } catch (error) {
                this.error = this.errorOf(error, this.$tc('alt-text-generator.page.settingsError'));
            }
        },

        async loadCredits() {
            try {
                const { credits } = await this.altTextGeneratorApiService.getCredits();
                this.credits = typeof credits === 'number' ? credits : null;
            } catch {
                this.credits = null;
            }
        },

        async scan() {
            this.loading = true;
            this.error = '';
            try {
                const data = await this.altTextGeneratorApiService.getImages('all');
                this.allImages = data.files;
                this.counts = { images: data.images, missing: data.missing };
                this.applyFilter();
            } catch (error) {
                this.error = this.errorOf(error, this.$tc('alt-text-generator.page.loadError'));
            } finally {
                this.loading = false;
            }
        },

        applyFilter() {
            const files = this.allImages;
            this.images = this.filter === 'missing' ? files.filter((file) => !file.alt) : files;
        },

        setFilter(filter) {
            this.filter = filter;
            this.applyFilter();
        },

        async saveSettings(partial) {
            const previous = this.settings;
            this.settings = { ...this.settings, ...partial };
            try {
                this.settings = await this.altTextGeneratorApiService.updateSettings(partial);
            } catch (error) {
                this.settings = previous;
                this.createNotificationError({ message: this.errorOf(error, this.$tc('alt-text-generator.page.settingsError')) });
            }
        },

        isWorking(id) {
            return this.working.includes(id);
        },

        valueOf(image) {
            return this.drafts[image.id] ?? image.alt ?? '';
        },

        setDraft(id, value) {
            this.drafts = { ...this.drafts, [id]: value };
        },

        toggle(id) {
            this.selected = this.selected.includes(id)
                ? this.selected.filter((item) => item !== id)
                : [...this.selected, id];
        },

        async generateOne(image) {
            this.working = [...this.working, image.id];
            this.failed = Object.fromEntries(Object.entries(this.failed).filter(([id]) => id !== image.id));
            try {
                const data = await this.altTextGeneratorApiService.generate(image.id, this.settings.language);
                this.setDraft(image.id, data.altText);
                if (typeof data.creditsLeft === 'number') {
                    this.credits = data.creditsLeft;
                }
                return { fatal: false };
            } catch (error) {
                const message = this.errorOf(error, this.$tc('alt-text-generator.page.generateError'));
                this.failed = { ...this.failed, [image.id]: message };
                // A missing key or no credits will fail every image, so stop instead of trying them all.
                return { fatal: /credits|API key/i.test(message) };
            } finally {
                this.working = this.working.filter((id) => id !== image.id);
            }
        },

        async generate(list) {
            if (list.length === 0) {
                return;
            }
            this.stopRequested = false;
            this.running = true;
            const queue = [...list];
            let fatal = false;
            const worker = async () => {
                while (queue.length > 0 && !this.stopRequested && !fatal) {
                    const result = await this.generateOne(queue.shift());
                    if (result.fatal) {
                        fatal = true;
                    }
                }
            };
            await Promise.all(Array.from({ length: Math.min(CONCURRENCY, list.length) }, worker));
            this.running = false;
            this.loadCredits();
        },

        stopRun() {
            this.stopRequested = true;
        },

        async saveAll() {
            this.saving = true;
            try {
                const items = this.changed.map(([id, text]) => ({ id, alt: text }));
                const { saved } = await this.altTextGeneratorApiService.save(items);
                this.createNotificationSuccess({ message: this.$tc('alt-text-generator.page.saved', saved, { count: saved }) });
                this.drafts = {};
                this.selected = [];
                await this.scan();
            } catch (error) {
                this.createNotificationError({ message: this.errorOf(error, this.$tc('alt-text-generator.page.saveError')) });
            } finally {
                this.saving = false;
            }
        },
    },
});
