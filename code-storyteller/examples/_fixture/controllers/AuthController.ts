import { authService } from '../services/AuthService';

class AuthController {
  async login(req, res) {
    try {
      const result = await authService.verify(req.body);
      res.json(result);
    } catch (err) {
      res.status(401).json({ error: err.message });
    }
  }
}

export const authController = new AuthController();
