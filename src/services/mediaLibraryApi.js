import { memberAdminApi } from './memberAdminApi';

/** Authorized image catalog. Never use this endpoint as an anonymous public gallery. */
export const mediaLibraryApi = {
  list: ({ page = 1, perPage = 24, folder = '', search = '' } = {}) => {
    const query = new URLSearchParams({ page: String(page), per_page: String(perPage) });
    if (folder) query.set('folder', folder);
    if (search) query.set('search', search);
    return memberAdminApi(`/api/admin/media?${query}`);
  },
  get: (id) => memberAdminApi(`/api/admin/media/${encodeURIComponent(id)}`),
  upload: (file, { folder = 'general', altText = '', caption = '' } = {}) => {
    const data = new FormData();
    data.append('file', file);
    data.append('folder', folder);
    data.append('alt_text', altText);
    data.append('caption', caption);
    return memberAdminApi('/api/admin/media', { data });
  },
  uploadPeople: (file, { folder = 'staff', altText = '' } = {}) => {
    if (!['staff','board'].includes(folder)) throw new Error('ไม่อนุญาตโฟลเดอร์รูปบุคลากรนี้');
    const data = new FormData();
    data.append('file',file);
    data.append('folder',folder);
    data.append('alt_text',altText);
    return memberAdminApi('/api/admin/media/people',{data});
  },
  update: (id, { altText = '', caption = '', version }) =>
    memberAdminApi(`/api/admin/media/${encodeURIComponent(id)}/update`, {
      data: { alt_text: altText, caption, version }
    }),
  remove: (id, version) =>
    memberAdminApi(`/api/admin/media/${encodeURIComponent(id)}/delete`, {
      data: { version }
    }),
};
