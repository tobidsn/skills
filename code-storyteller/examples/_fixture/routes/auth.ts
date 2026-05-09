import { Router } from 'express';
import { validateLoginPayload } from '../middleware/validateLoginPayload';
import { authController } from '../controllers/AuthController';

export const authRouter = Router();
authRouter.post('/login', validateLoginPayload, authController.login);
