'use client';
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useEffect, useId, useState } from 'react';
import { useAuth } from './AuthProvider';
import { useBranding } from './BrandingProvider';
import { ImpersonationBanner } from './ImpersonationBanner';
import { NotificationBell } from './NotificationBell';
import { UserMenu } from './UserMenu';

const appLinks = [
  { href: '/', label: 'Dashboard', match: (path: string) => path === '/' },
  { href: '/calendar', label: 'Calendar', match: (path: string) => path.startsWith('/calendar') },
  { href: '/meetings', label: 'Meetings', match: (path: string) => path.startsWith('/meetings') },
  { href: '/assistant', label: 'Meeting Assistant', match: (path: string) => path.startsWith('/assistant') },
  { href: '/settings', label: 'Settings', match: (path: string) => path.startsWith('/settings') },
];

const adminLinks = [
  { href: '/admin/users', label: 'User Management' },
  { href: '/admin/roles', label: 'Roles and Permissions' },
  { href: '/admin/activity', label: 'Activity Tracking' },
  { href: '/admin/meetings-config', label: 'Meetings Configurator' },
  { href: '/settings', label: 'Settings' },
];

function AdministrationNav({ pathname }: { pathname: string }) {
  const isChildActive = pathname.startsWith('/admin') || pathname.startsWith('/settings');
  const [open, setOpen] = useState(isChildActive);
  const panelId = useId();

  useEffect(() => {
    if (isChildActive) setOpen(true);
  }, [isChildActive]);

  return (
    <div className={`nav-group${open ? ' is-open' : ''}`}>
      <button
        type="button"
        className={`nav-group-toggle${isChildActive ? ' active' : ''}`}
        aria-expanded={open}
        aria-controls={panelId}
        onClick={() => setOpen((value) => !value)}
      >
        Administration
        <svg className="nav-group-chevron" viewBox="0 0 16 16" aria-hidden="true" fill="none">
          <path
            d="M4 6.5 8 10.5 12 6.5"
            stroke="currentColor"
            strokeWidth="1.75"
            strokeLinecap="round"
            strokeLinejoin="round"
          />
        </svg>
      </button>
      <div className="nav-group-panel" id={panelId} aria-hidden={!open} inert={!open}>
        <div className="nav-group-panel-inner">
          {adminLinks.map((link) => (
            <Link
              key={link.href}
              className={`nav-group-link${pathname.startsWith(link.href) ? ' active' : ''}`}
              href={link.href}
              tabIndex={open ? undefined : -1}
            >
              {link.label}
            </Link>
          ))}
        </div>
      </div>
    </div>
  );
}

export function AppShell({ children }: { children: React.ReactNode }) {
  const { user, loading, logout, isSuperAdmin } = useAuth();
  const { logoUrl } = useBranding();
  const pathname = usePathname();
  const router = useRouter();
  const visibleAppLinks = isSuperAdmin
    ? appLinks.filter((link) => link.href !== '/settings')
    : appLinks;

  useEffect(() => {
    if (!loading && !user && pathname != '/login') router.replace('/login');
  }, [loading, user, pathname, router]);

  useEffect(() => {
    if (!loading && user && pathname.startsWith('/admin') && !isSuperAdmin) {
      router.replace('/');
    }
  }, [loading, user, pathname, isSuperAdmin, router]);

  if (pathname === '/login') return <>{children}</>;
  if (loading || !user)
    return (
      <div className="center-screen">
        <div className="loader" />
        <p>Loading workspace...</p>
      </div>
    );

  if (pathname.startsWith('/admin') && !isSuperAdmin) {
    return (
      <div className="center-screen">
        <div className="loader" />
        <p>Redirecting...</p>
      </div>
    );
  }

  return (
    <div className="app-shell">
      <aside className="sidebar">
        <Link href="/" className="brand">
          <img
            src={logoUrl}
            alt={user.tenant?.name ?? 'Organisation'}
            className="brand-logo"
          />
        </Link>
        <nav>
          {visibleAppLinks.map((link) => (
            <Link key={link.href} className={link.match(pathname) ? 'active' : ''} href={link.href}>
              {link.label}
            </Link>
          ))}
          {isSuperAdmin ? <AdministrationNav pathname={pathname} /> : null}
        </nav>
      </aside>
      <div className="shell-column">
        <div className="shell-top">
          <ImpersonationBanner />
          <header className="top-nav">
            <div className="top-nav-actions">
              <NotificationBell />
              <UserMenu
                name={user.name}
                email={user.email}
                avatarUrl={user.avatar_url}
                onLogout={() => void logout()}
              />
            </div>
          </header>
        </div>
        <main className="main-content">{children}</main>
      </div>
    </div>
  );
}
