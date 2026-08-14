export const metadata = {
  title: "Academia API",
  description: "Multi-tenant School Management SaaS — backend API",
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
