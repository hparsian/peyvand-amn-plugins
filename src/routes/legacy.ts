import { Router, Request, Response } from 'express';

export const legacyRouter = Router();

const notMigrated = (_req: Request, res: Response) => {
  res.status(501).json({ error: 'Not yet migrated' });
};

legacyRouter.all('/wp-cron', notMigrated);
legacyRouter.all('/sync/*', notMigrated);
legacyRouter.all('/content-writer/*', notMigrated);
legacyRouter.all('/daily-feed/*', notMigrated);
legacyRouter.all('/2fa/*', notMigrated);
legacyRouter.all('/contact/*', notMigrated);
legacyRouter.all('/site-operator/*', notMigrated);
