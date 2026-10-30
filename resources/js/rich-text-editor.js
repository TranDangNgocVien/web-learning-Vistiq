import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import { TableKit } from '@tiptap/extension-table';
document.querySelectorAll('[data-rich-editor]').forEach(textarea => {
    const wrapper = document.createElement('div'), toolbar = document.createElement('div'), surface = document.createElement('div');
    wrapper.className = 'rich-editor'; toolbar.className = 'assessment-actions'; surface.className = 'rich-editor-surface';
    textarea.before(wrapper); wrapper.append(toolbar, surface, textarea); textarea.hidden = true;
    const editor = new Editor({ element: surface, extensions: [StarterKit, Image, TableKit.configure({ table: { resizable: true } })], content: textarea.value, onUpdate: ({ editor: instance }) => { textarea.value = instance.getHTML(); textarea.dispatchEvent(new Event('input', { bubbles: true })); } });
    const actions = { 'Đậm': () => editor.chain().focus().toggleBold().run(), 'Nghiêng': () => editor.chain().focus().toggleItalic().run(), 'Heading': () => editor.chain().focus().toggleHeading({ level: 2 }).run(), 'Đoạn văn': () => editor.chain().focus().setParagraph().run(), 'Danh sách': () => editor.chain().focus().toggleBulletList().run(), 'Bảng': () => editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run(), 'Ảnh': () => { const src = prompt('URL ảnh HTTPS hoặc đường dẫn media của dự án'); if (src && (/^https:\/\//.test(src) || src.startsWith('/'))) { editor.chain().focus().setImage({ src }).run(); } }, 'Paragraph label': () => { const label = prompt('Nhãn đoạn (A, B, C...)'); if (label) { editor.chain().focus().insertContent({ type: 'paragraph', content: [{ type: 'text', text: `${label}. ` }] }).run(); } }, 'HTML / Text': () => { textarea.hidden = !textarea.hidden; surface.hidden = !textarea.hidden; } };
    Object.entries(actions).forEach(([label, action]) => { const button = document.createElement('button'); button.type = 'button'; button.className = 'button button-quiet'; button.textContent = label; button.addEventListener('click', action); toolbar.append(button); });
    textarea.addEventListener('change', () => editor.commands.setContent(textarea.value, { emitUpdate: false }));
});
