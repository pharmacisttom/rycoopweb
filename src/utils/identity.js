/** Hydrate from the authenticated server response, never from a cached account ID. */
export function mergeServerIdentity(current, server) {
  const id=Number(server?.id);
  if(!Number.isSafeInteger(id)||id<=0)return null;
  return {...current,...server,id,role:server.role||server.role_slug,
    roleName:server.role_name||server.roleName,
    permissions:Array.isArray(server.permissions)?server.permissions.filter(value=>typeof value==='string'):[],
    effective_permissions:Array.isArray(server.effective_permissions)?server.effective_permissions.filter(value=>typeof value==='string'):[]};
}
export function hasEffectivePermission(user,permission){return !!user&&(user.role==='super_admin'||[...(user.permissions||[]),...(user.effective_permissions||[])].includes(permission));}
