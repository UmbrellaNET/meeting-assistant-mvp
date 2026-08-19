import type { Metadata } from 'next';
import { Manrope } from 'next/font/google';
import './globals.css';
import { AuthProvider } from '@/components/AuthProvider';
import { AppShell } from '@/components/AppShell';

const manrope = Manrope({
  subsets: ['latin'],
  variable: '--font-manrope',
  display: 'swap',
});

export const metadata: Metadata = {
  title: 'Meeting Intelligence MVP',
  description: 'AI-powered meeting capture and transcription workspace',
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body className={manrope.className}>
        <AuthProvider>
          <AppShell>{children}</AppShell>
        </AuthProvider>
      </body>
    </html>
  );
}
