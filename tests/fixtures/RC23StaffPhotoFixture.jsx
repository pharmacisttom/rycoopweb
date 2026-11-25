import React from 'react';
import {createRoot} from 'react-dom/client';
import {MemoryRouter} from 'react-router-dom';
import PublicContentAdminPage from '../../src/pages/PublicContentAdminPage';

function Fixture(){
  return <MemoryRouter initialEntries={['/admin/public-content?section=staff']}>
    <PublicContentAdminPage/>
  </MemoryRouter>;
}
createRoot(document.getElementById('root')).render(<Fixture/>);
