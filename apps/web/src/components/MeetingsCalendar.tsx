'use client';
import { useEffect, useMemo, useState } from 'react';
import { useRouter } from 'next/navigation';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import type { EventClickArg } from '@fullcalendar/core';
import { api } from '@/lib/api';
import type { Meeting, Paginated } from '@/lib/types';

export function MeetingsCalendar() {
  const router = useRouter();
  const [meetings, setMeetings] = useState<Meeting[]>([]);
  const [error, setError] = useState('');

  useEffect(() => {
    api<Paginated<Meeting>>('/meetings?per_page=100')
      .then((page) => setMeetings(page.data))
      .catch((e) => setError(e.message));
  }, []);

  const events = useMemo(
    () =>
      meetings.map((meeting) => ({
        id: meeting.id,
        title: meeting.title,
        start: meeting.scheduled_start_at || meeting.created_at,
      })),
    [meetings],
  );

  const onEventClick = (info: EventClickArg) => {
    info.jsEvent.preventDefault();
    router.push(`/meetings/${info.event.id}`);
  };

  return (
    <section className="panel calendar-panel">
      {error && <div className="error-box">{error}</div>}
      <FullCalendar
        plugins={[dayGridPlugin, timeGridPlugin, interactionPlugin]}
        initialView="dayGridMonth"
        headerToolbar={{
          left: 'prev,next today',
          center: 'title',
          right: 'dayGridMonth,timeGridWeek',
        }}
        height="auto"
        events={events}
        eventClick={onEventClick}
      />
    </section>
  );
}
