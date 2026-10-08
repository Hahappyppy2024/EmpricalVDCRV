import {ApiError} from './errors.js';
export function repository(db){
 const one=(table,id,label)=>{const row=db.prepare(`SELECT * FROM ${table} WHERE id=?`).get(id);if(!row)throw new ApiError(404,'not_found',`${label} was not found.`);return row;};
 return{user:id=>one('users',id,'User'),content:id=>one('content',id,'Content'),media:id=>one('media_assets',id,'Media asset'),template:id=>one('templates',id,'Template'),comment:id=>one('comments',id,'Comment'),role:id=>one('roles',id,'Role'),integration:id=>one('integrations',id,'Integration'),redirect:id=>one('redirects',id,'Redirect')};
}
