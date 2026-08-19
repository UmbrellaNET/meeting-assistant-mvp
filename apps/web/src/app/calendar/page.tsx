'use client';
import { useState } from 'react';
import { MeetingsCalendar } from '@/components/MeetingsCalendar';

export default function CalendarPage() {
  const [notice, setNotice] = useState('');

  const showUnconfigured = (provider: string) => {
    setNotice(`${provider} OAuth is not configured yet.`);
  };

  return (
    <div>
      <header className="page-header">
        <div>
          <h1>Calendar</h1>
          <p>Month and week view of meetings in this tenant.</p>
        </div>
      </header>
      <div className="calendar-layout">
        <MeetingsCalendar />
        <section className="panel">
          <div className="panel-heading">
            <div>
              <h2>Calendar integrations</h2>
              <p>Connect an external calendar when OAuth is available.</p>
            </div>
          </div>
          <div className="integration-list">
            <div className="integration-row">
              <div>
                <strong>Google Calendar</strong>
                <small>Sync meetings from Google</small>
              </div>
              <button className="secondary-button" type="button" onClick={() => showUnconfigured('Google Calendar')}>
                Connect
              </button>
            </div>
            <div className="integration-row">
              <div>
                <strong>Outlook</strong>
                <small>Sync meetings from Microsoft 365</small>
              </div>
              <button className="secondary-button" type="button" onClick={() => showUnconfigured('Outlook')}>
                Connect
              </button>
            </div>
          </div>
          {notice && <p className="integration-note">{notice}</p>}
        </section>
      </div>
    </div>
  );
}
