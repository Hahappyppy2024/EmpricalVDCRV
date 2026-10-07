import os
from flask import Flask, render_template
from config import Config
from database import init_db, seed_admin

# Import Controllers
from controllers.auth import auth_bp
from controllers.course import course_bp
from controllers.post import post_bp
from controllers.Auth.assignment_d import asg_bp
from controllers.upload import up_bp
from controllers.quiz import quiz_bp
from controllers.resource import resource_bp
from controllers.audit import audit_bp

def create_app(config_class=Config):
    app = Flask(__name__)
    app.config.from_object(config_class)



    # # Ensure upload folder exists
    # os.makedirs(app.config['UPLOAD_FOLDER'], exist_ok=True)
    upload_folder = app.config.get('UPLOAD_FOLDER')
    if upload_folder:
        os.makedirs(upload_folder, exist_ok=True)
    # Register Blueprints
    app.register_blueprint(auth_bp)
    app.register_blueprint(course_bp)
    app.register_blueprint(post_bp)
    app.register_blueprint(asg_bp)
    app.register_blueprint(up_bp)
    app.register_blueprint(quiz_bp)


    # 在 app.py 顶部导入

    # 在 create_app 函数中注册
    app.register_blueprint(resource_bp)

    # Admin audit log (A09)
    app.register_blueprint(audit_bp)

    # for rule in app.url_map.iter_rules():
    #     if "assignments" in rule.rule:
    #         print("ASSIGN:", rule.rule, rule.methods, "->", rule.endpoint)
    #

    # Main Route
    @app.route('/')
    def index():
        return render_template('index.html')

    # DB Initialization Logic
    # 如果是测试模式（TestConfig），在应用上下文中初始化
    if app.config['TESTING']:
        with app.app_context():
            init_db()
            seed_admin()

    return app


if __name__ == '__main__':
    # 生产/开发环境启动
    app = create_app()

    # 确保数据库表已创建（开发环境下）
    # 这里直接调用，因为 TESTING=False 时 create_app 内部不调用
    init_db()
    seed_admin()

    app.run(debug=True, port=5000)
