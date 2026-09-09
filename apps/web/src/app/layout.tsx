import type { Metadata } from 'next';
import { Manrope } from 'next/font/google';
import './globals.css';
import { AuthProvider } from '@/components/AuthProvider';
import { BrandingProvider } from '@/components/BrandingProvider';
import { AppShell } from '@/components/AppShell';
import { ToastProvider } from '@/components/ToastProvider';

const manrope = Manrope({
  subsets: ['latin'],
  variable: '--font-manrope',
  display: 'swap',
});

export const metadata: Metadata = {
  title: 'Meeting Intelligence MVP',
  description: 'AI-powered meeting capture and transcription workspace',
  icons: {
    icon: [
      { url: '/favicon-16.png', sizes: '16x16', type: 'image/png' },
      { url: '/favicon.png', sizes: '32x32', type: 'image/png' },
      { url: '/icon-192.png', sizes: '192x192', type: 'image/png' },
    ],
    apple: { url: '/apple-touch-icon.png', sizes: '180x180' },
  },
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body className={manrope.className}>
        <AuthProvider>
          <BrandingProvider>
            <ToastProvider>
              <AppShell>{children}</AppShell>
            </ToastProvider>
          </BrandingProvider>
        </AuthProvider>
      </body>
    </html>
  );
}
