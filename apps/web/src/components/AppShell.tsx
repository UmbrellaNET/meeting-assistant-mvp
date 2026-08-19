'use client';
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useEffect } from 'react';
import { useAuth } from './AuthProvider';
import { NotificationBell } from './NotificationBell';
import { UserMenu } from './UserMenu';

export function AppShell({ children }: { children: React.ReactNode }) {
  const { user, loading, logout } = useAuth();
  const pathname = usePathname();
  const router = useRouter();

  useEffect(() => {
    if (!loading && !user && pathname != '/login') router.replace('/login');
  }, [loading, user, pathname, router]);

  if (pathname === '/login') return <>{children}</>;
  if (loading || !user)
    return (
      <div className="center-screen">
        <div className="loader" />
        <p>Loading workspace...</p>
      </div>
    );

  return (
    <div className="app-shell">
      <aside className="sidebar">
        <Link href="/" className="brand">
          <img
            src="/un-logo-horizontal-light.webp"
            alt="UmbrellaNET"
            className="brand-logo"
          />
        </Link>
        <nav>
          <Link className={pathname === '/' ? 'active' : ''} href="/">
            Overview
          </Link>
          <Link className={pathname.startsWith('/calendar') ? 'active' : ''} href="/calendar">
            Calendar
          </Link>
          <Link className={pathname.startsWith('/meetings') ? 'active' : ''} href="/meetings">
            Meetings
          </Link>
          <span className="nav-section">Admin</span>
          <Link className={pathname.startsWith('/admin/users') ? 'active' : ''} href="/admin/users">
            User management
          </Link>
          <Link className={pathname.startsWith('/admin/roles') ? 'active' : ''} href="/admin/roles">
            Roles and permissions
          </Link>
          <Link className={pathname.startsWith('/admin/activity') ? 'active' : ''} href="/admin/activity">
            Activity tracking
          </Link>
          <Link className={pathname.startsWith('/admin/meetings-config') ? 'active' : ''} href="/admin/meetings-config">
            Meetings Configurator
          </Link>
        </nav>
      </aside>
      <div className="shell-column">
        <header className="top-nav">
          <div className="top-nav-actions">
            <NotificationBell />
            <UserMenu
              name={user.name}
              email={user.email}
              onLogout={() => void logout()}
            />
          </div>
        </header>
        <main className="main-content">{children}</main>
      </div>
    </div>
  );
}
