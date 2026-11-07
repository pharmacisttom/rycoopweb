/** Hydrate from the authenticated server response, never from a cached account ID. */
export function mergeServerIdentity(current, server) {
  const id=Number(server?.id);
  if(!Number.isSafeInteger(id)||id<=0)return null;
  return {...current,...server,id,role:server.role||server.role_slug,
    roleName:server.role_name||server.roleName,
    permissions:Array.isArray(server.permissions)?server.permissions.filter(value=>typeof value==='string'):[],
    effective_permissions:Array.isArray(server.effective_permissions)?server.effective_permissions.filter(value=>typeof value==='string'):[]};
}
export function hasEffectivePermission(user, permission) {
  if (!user) return false;
  if (user.role === 'super_admin') return true;
  const perms = new Set([...(user.permissions || []), ...(user.effective_permissions || [])]);
  if (perms.has(permission)) return true;

  const aliases = {
    'content.view': ['news.view', 'announcements.view', 'hero_slides.view', 'popups.view'],
    'people.view': ['boards.view', 'staff.view', 'users.view'],
    'products.view': ['loan_products.view', 'deposit_products.view', 'interest_rates.view'],
    'settings.view': ['settings.view', 'eservice_links.view']
  };
  if (aliases[permission]) {
    return aliases[permission].some(p => perms.has(p));
  }
  return false;
}
