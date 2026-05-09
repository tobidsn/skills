import type { Request, Response, NextFunction } from 'express';

export const validateLoginPayload = (req: Request, res: Response, next: NextFunction) => {
  const { email, password } = req.body ?? {};
  if (!email || !password) {
    return res.status(400).json({ error: 'email and password are required' });
  }
  if (typeof email !== 'string' || typeof password !== 'string') {
    return res.status(400).json({ error: 'invalid types' });
  }
  next();
};
