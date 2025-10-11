import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import ArticlePublicContent from '@/components/custom/models/article/ArticlePublicContent.vue';
import { createI18n } from 'vue-i18n';

// Mock i18n
const i18n = createI18n({
    legacy: false,
    locale: 'en',
    messages: {
        en: {},
    },
});

describe('ArticlePublicContent - Responsive Grid', () => {
    const mockArticle = {
        id: 1,
        alias: 'test-article',
        campaign_id: 1,
        name: 'Test Article',
        status: 'published',
        is_draft: false,
        has_index: false,
        has_sidebar: false,
    };

    describe('Grid Responsive Classes', () => {
        it('should have responsive grid classes: grid-cols-1 md:grid-cols-2 xl:grid-cols-3', () => {
            const mockBlocks = [
                {
                    id: 1,
                    type: 'text',
                    content: 'Block 1',
                    disposition: 'content',
                    order: 1,
                    column_span: 1,
                },
                {
                    id: 2,
                    type: 'text',
                    content: 'Block 2',
                    disposition: 'content',
                    order: 2,
                    column_span: 2,
                },
            ];

            const wrapper = mount(ArticlePublicContent, {
                props: {
                    article: mockArticle,
                    blocks: mockBlocks,
                },
                global: {
                    plugins: [i18n],
                    stubs: {
                        BlockWrapper: true,
                    },
                },
            });

            // Find the grid container
            const gridContainer = wrapper.find('.grid');
            expect(gridContainer.exists()).toBe(true);
            expect(gridContainer.classes()).toContain('grid-cols-1');
            expect(gridContainer.classes()).toContain('md:grid-cols-2');
            expect(gridContainer.classes()).toContain('xl:grid-cols-3');
        });
    });

    describe('Column Span Responsive Classes', () => {
        it('should apply col-span-1 for blocks with span 1', () => {
            const mockBlocks = [
                {
                    id: 1,
                    type: 'text',
                    content: 'Block 1',
                    disposition: 'content',
                    order: 1,
                    column_span: 1,
                },
            ];

            const wrapper = mount(ArticlePublicContent, {
                props: {
                    article: mockArticle,
                    blocks: mockBlocks,
                },
                global: {
                    plugins: [i18n],
                    stubs: {
                        BlockWrapper: true,
                    },
                },
            });

            const blockContainer = wrapper.find('.grid > div');
            expect(blockContainer.classes()).toContain('col-span-1');
        });

        it('should apply responsive col-span for blocks with span 2', () => {
            const mockBlocks = [
                {
                    id: 1,
                    type: 'text',
                    content: 'Block 1',
                    disposition: 'content',
                    order: 1,
                    column_span: 2,
                },
            ];

            const wrapper = mount(ArticlePublicContent, {
                props: {
                    article: mockArticle,
                    blocks: mockBlocks,
                },
                global: {
                    plugins: [i18n],
                    stubs: {
                        BlockWrapper: true,
                    },
                },
            });

            const blockContainer = wrapper.find('.grid > div');
            expect(blockContainer.classes()).toContain('col-span-1');
            expect(blockContainer.classes()).toContain('md:col-span-2');
        });

        it('should apply responsive col-span for blocks with span 3', () => {
            const mockBlocks = [
                {
                    id: 1,
                    type: 'text',
                    content: 'Block 1',
                    disposition: 'content',
                    order: 1,
                    column_span: 3,
                },
            ];

            const wrapper = mount(ArticlePublicContent, {
                props: {
                    article: mockArticle,
                    blocks: mockBlocks,
                },
                global: {
                    plugins: [i18n],
                    stubs: {
                        BlockWrapper: true,
                    },
                },
            });

            const blockContainer = wrapper.find('.grid > div');
            expect(blockContainer.classes()).toContain('col-span-1');
            expect(blockContainer.classes()).toContain('md:col-span-2');
            expect(blockContainer.classes()).toContain('xl:col-span-3');
        });

        it('should render multiple blocks with correct responsive classes', () => {
            const mockBlocks = [
                {
                    id: 1,
                    type: 'text',
                    content: 'Block 1',
                    disposition: 'content',
                    order: 1,
                    column_span: 1,
                },
                {
                    id: 2,
                    type: 'text',
                    content: 'Block 2',
                    disposition: 'content',
                    order: 2,
                    column_span: 2,
                },
                {
                    id: 3,
                    type: 'text',
                    content: 'Block 3',
                    disposition: 'content',
                    order: 3,
                    column_span: 3,
                },
            ];

            const wrapper = mount(ArticlePublicContent, {
                props: {
                    article: mockArticle,
                    blocks: mockBlocks,
                },
                global: {
                    plugins: [i18n],
                    stubs: {
                        BlockWrapper: true,
                    },
                },
            });

            const blockContainers = wrapper.findAll('.grid > div');
            expect(blockContainers).toHaveLength(3);

            // Block 1: span 1
            expect(blockContainers[0].classes()).toContain('col-span-1');

            // Block 2: span 2
            expect(blockContainers[1].classes()).toContain('col-span-1');
            expect(blockContainers[1].classes()).toContain('md:col-span-2');

            // Block 3: span 3
            expect(blockContainers[2].classes()).toContain('col-span-1');
            expect(blockContainers[2].classes()).toContain('md:col-span-2');
            expect(blockContainers[2].classes()).toContain('xl:col-span-3');
        });
    });

    describe('Blocks Sorting', () => {
        it('should sort blocks by order property', () => {
            const mockBlocks = [
                {
                    id: 3,
                    type: 'text',
                    content: 'Block 3',
                    disposition: 'content',
                    order: 3,
                    column_span: 1,
                },
                {
                    id: 1,
                    type: 'text',
                    content: 'Block 1',
                    disposition: 'content',
                    order: 1,
                    column_span: 1,
                },
                {
                    id: 2,
                    type: 'text',
                    content: 'Block 2',
                    disposition: 'content',
                    order: 2,
                    column_span: 1,
                },
            ];

            const wrapper = mount(ArticlePublicContent, {
                props: {
                    article: mockArticle,
                    blocks: mockBlocks,
                },
                global: {
                    plugins: [i18n],
                    stubs: {
                        BlockWrapper: {
                            template: '<div>{{ block.content }}</div>',
                            props: ['block'],
                        },
                    },
                },
            });

            const blockContainers = wrapper.findAll('.grid > div');
            expect(blockContainers[0].text()).toContain('Block 1');
            expect(blockContainers[1].text()).toContain('Block 2');
            expect(blockContainers[2].text()).toContain('Block 3');
        });
    });
});

