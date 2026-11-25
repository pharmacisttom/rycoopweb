import assert from 'node:assert/strict';
import {applyPersonPhotoToDraft} from '../src/utils/personPhotoDraft.js';

const originals=[{id:11,name:'Fixture Staff A',image:'/before-a.webp'},
 {id:12,name:'Fixture Staff B',image:'/before-b.webp'}];
const next=applyPersonPhotoToDraft(originals,{
 currentSection:'staff',targetSection:'staff',targetId:12,targetIndex:1,url:'/storage/uploads/staff/new.webp'
});
assert.notEqual(next,originals);
assert.equal(next[0].image,'/before-a.webp');
assert.equal(next[1].image,'/storage/uploads/staff/new.webp');
assert.equal(originals[1].image,'/before-b.webp');
assert.equal(applyPersonPhotoToDraft(originals,{
 currentSection:'boards',targetSection:'staff',targetId:12,targetIndex:1,url:'/x.webp'
}),originals,'late staff photo cannot affect board section');
assert.equal(applyPersonPhotoToDraft(originals,{
 currentSection:'staff',targetSection:'staff',targetId:11,targetIndex:1,url:'/x.webp'
}),originals,'photo cannot attach after person selection changed');
assert.equal(applyPersonPhotoToDraft(originals,{
 currentSection:'staff',targetSection:'staff',targetId:12,targetIndex:8,url:'/x.webp'
}),originals,'photo cannot attach after row removal');
assert.equal(applyPersonPhotoToDraft(originals,{
 currentSection:'staff',targetSection:'staff',targetId:12,targetIndex:1,url:''
}),originals);
console.log('RC23_PERSON_PHOTO_DRAFT_SECTION_ISOLATION=PASS');
