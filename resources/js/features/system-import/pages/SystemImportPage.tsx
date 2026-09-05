import React, { useState } from 'react';
import { Container, Box, Tabs, Tab } from '@mui/material';
import { PageHeader } from '@/components/layout/PageHeader';
import UISPDeviceTab from '../tabs/UISPDeviceTab';
import UISPSiteTab from '../tabs/UISPSiteTab';
import NMSDeviceTab from '../tabs/NMSDeviceTab';
import NMSSiteTab from '../tabs/NMSSiteTab';

interface TabPanelProps {
  children?: React.ReactNode;
  index: number;
  value: number;
}

function TabPanel(props: TabPanelProps) {
  const { children, value, index, ...other } = props;
  return (
    <div role="tabpanel" hidden={value !== index} id={`system-import-tabpanel-${index}`} {...other}>
      {value === index && <Box sx={{ p: 0 }}>{children}</Box>}
    </div>
  );
}

const SystemImportPage: React.FC = () => {
  const [tabValue, setTabValue] = useState(0);

  const handleChange = (_event: React.SyntheticEvent, newValue: number) => {
    setTabValue(newValue);
  };

  return (
    <Container maxWidth="xl">
      <PageHeader title="System Import" subtitle="Manage imports from UISP and NMS sources" />
      
      <Box sx={{ borderBottom: 1, borderColor: 'divider', mb: 3 }}>
        <Tabs value={tabValue} onChange={handleChange} aria-label="system import tabs">
          <Tab label="UISP Devices" />
          <Tab label="UISP Sites" />
          <Tab label="NMS Devices" />
          <Tab label="NMS Sites" />
        </Tabs>
      </Box>

      <TabPanel value={tabValue} index={0}>
        <UISPDeviceTab />
      </TabPanel>
      <TabPanel value={tabValue} index={1}>
        <UISPSiteTab />
      </TabPanel>
      <TabPanel value={tabValue} index={2}>
        <NMSDeviceTab />
      </TabPanel>
      <TabPanel value={tabValue} index={3}>
        <NMSSiteTab />
      </TabPanel>
    </Container>
  );
};

export default SystemImportPage;
